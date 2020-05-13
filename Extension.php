<?php namespace IgniterLabs\SmsNotify;

use Event;
use IgniterLabs\SmsNotify\Classes\Manager;
use IgniterLabs\SmsNotify\Classes\OtpManager;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use System\Classes\BaseExtension;

/**
 * SmsNotify Extension Information File
 */
class Extension extends BaseExtension
{
    /**
     * Register method, called when the extension is first registered.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(\Igniter\Flame\Notifications\NotificationServiceProvider::class);
        $this->app->register(\Illuminate\Notifications\NexmoChannelServiceProvider::class);
        $this->app->register(\NotificationChannels\Twilio\TwilioProvider::class);
        $this->app->register(\NotificationChannels\Clickatell\ClickatellServiceProvider::class);
        $this->app->register(\NotificationChannels\Plivo\PlivoServiceProvider::class);

        AliasLoader::getInstance()->alias('Notification', \Illuminate\Support\Facades\Notification::class);
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return void
     */
    public function boot()
    {
        $this->bindNotificationEvents();

        $this->bindRegisterEvents();
    }

    /**
     * Registers any front-end components implemented in this extension.
     *
     * @return array
     */
    public function registerComponents()
    {
        return [
            'IgniterLabs\SmsNotify\Components\OTPVerify' => [
                'code' => 'otpVerify',
                'name' => 'igniterlabs.smsnotify::default.component_name',
                'description' => 'igniterlabs.smsnotify::default.component_desc',
            ],
        ];
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label' => 'igniterlabs.smsnotify::default.settings.text_title',
                'description' => 'igniterlabs.smsnotify::default.settings.text_desc',
                'icon' => 'fa fa-search-plus',
                'model' => 'IgniterLabs\SmsNotify\Models\Settings',
                'permissions' => ['IgniterLabs.SmsNotify.*'],
            ],
        ];
    }

    /**
     * Registers any admin permissions used by this extension.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return [
            'IgniterLabs.SmsNotify.Manage' => [
                'description' => 'Manage SMS notification channels and templates',
                'group' => 'module',
            ],
        ];
    }

    public function registerNavigation()
    {
        return [
            'design' => [
                'child' => [
                    'notification_templates' => [
                        'priority' => 999,
                        'class' => 'notification_templates',
                        'href' => admin_url('igniterlabs/smsnotify/templates'),
                        'title' => lang('igniterlabs.smsnotify::default.template.text_title'),
                        'permission' => 'IgniterLabs.SmsNotify.Manage',
                    ],
                ],
            ],
            'tools' => [
                'child' => [
                    'notification_channels' => [
                        'priority' => 999,
                        'class' => 'notification_channels',
                        'href' => admin_url('igniterlabs/smsnotify/channels'),
                        'title' => lang('igniterlabs.smsnotify::default.channel.text_title'),
                        'permission' => 'IgniterLabs.SmsNotify.Manage',
                    ],
                ],
            ],
        ];
    }

    public function registerAutomationRules()
    {
        return [
            'events' => [],
            'actions' => [
                \IgniterLabs\SmsNotify\AutomationRules\Actions\SendSmsNotification::class,
            ],
            'conditions' => [],
            'presets' => [
                'smsnotify_new_order_status' => [
                    'name' => 'Send an SMS message when an order status is updated',
                    'event' => \Igniter\Cart\AutomationRules\Events\NewOrderStatus::class,
                    'actions' => [
                        \IgniterLabs\SmsNotify\AutomationRules\Actions\SendSmsNotification::class => [
                            'template' => 'igniterlabs.smsnotify::_sms.order_status_changed',
                            'send_to' => 'customer',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function registerSmsNotifications()
    {
        return [
            'channels' => [
                'twilio' => \IgniterLabs\SmsNotify\Notifications\Channels\Twilio::class,
                'nexmo' => \IgniterLabs\SmsNotify\Notifications\Channels\Nexmo::class,
                'clickatell' => \IgniterLabs\SmsNotify\Notifications\Channels\Clickatell::class,
                'plivo' => \IgniterLabs\SmsNotify\Notifications\Channels\Plivo::class,
            ],
            'templates' => [
                'igniterlabs.smsnotify::_sms.new_order' => 'igniterlabs.smsnotify::default.template.text_order_placed',
                'igniterlabs.smsnotify::_sms.new_reservation' => 'igniterlabs.smsnotify::default.template.text_new_reservation',
                'igniterlabs.smsnotify::_sms.order_assigned' => 'igniterlabs.smsnotify::default.template.text_order_assigned',
                'igniterlabs.smsnotify::_sms.order_confirmed' => 'igniterlabs.smsnotify::default.template.text_order_confirmed',
                'igniterlabs.smsnotify::_sms.order_status_changed' => 'igniterlabs.smsnotify::default.template.text_order_status_changed',
                'igniterlabs.smsnotify::_sms.reservation_assigned' => 'igniterlabs.smsnotify::default.template.text_reservation_assigned',
                'igniterlabs.smsnotify::_sms.reservation_confirmed' => 'igniterlabs.smsnotify::default.template.text_reservation_confirmed',
                'igniterlabs.smsnotify::_sms.reservation_status_changed' => 'igniterlabs.smsnotify::default.template.text_reservation_status_changed',
            ],
        ];
    }

    protected function bindNotificationEvents()
    {
        Event::listen('notification.beforeRegister', function () {
            Manager::instance()->applyNotificationConfigValues();
        });

        Event::listen(NotificationSending::class, function ($event) {
//            $channel = Settings::findChannelByName($event->channel);
//
//            return $channel ? $channel->isEnabled() : TRUE;
        });

        Event::listen(NotificationFailed::class, function ($event) {
            \Log::error(array_get($event->data, 'message'));
        });
    }

    protected function bindRegisterEvents()
    {
        Event::listen('igniter.user.beforeRegister', function ($data) {
            Manager::instance()->applyNotificationConfigValues();
        });
    }

    protected function bindOtpVerificationEvents()
    {
        Event::listen('igniter.user.beforeAuthenticate', function ($component, $credentials) {
            OtpManager::instance()->beforeUserAuthenticate($credentials);
        });
    }
}
