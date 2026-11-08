<?php

namespace Database\Seeders;

use App\Filament\Widgets\CouponStats;
use App\Filament\Widgets\OrderStatusStats;
use App\Filament\Widgets\ShipmentStats;
use App\Filament\Widgets\ShopStats;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Wallacemartinss\FilamentOnboarding\Enums\CompletionMode;
use Wallacemartinss\FilamentOnboarding\Enums\ConditionOperator;
use Wallacemartinss\FilamentOnboarding\Enums\ConditionType;
use Wallacemartinss\FilamentOnboarding\Enums\StepType;
use Wallacemartinss\FilamentOnboarding\Facades\Onboarding;
use Wallacemartinss\FilamentOnboarding\Models\OnboardingCondition;
use Wallacemartinss\FilamentOnboarding\Models\OnboardingFlow;
use Wallacemartinss\FilamentOnboarding\Models\OnboardingStep;

/**
 * Seeds an onboarding journey for every area of the admin panel.
 *
 * Safe to run repeatedly: flows, steps and conditions are matched on their key.
 */
class OnboardingSeeder extends Seeder
{
    private const PANEL = 'admin';

    public function run(): void
    {
        $this->seedConditions();

        foreach ($this->flows() as $order => $flow) {
            $this->seedFlow($flow, $order);
        }

        Onboarding::flushCache();
    }

    private function seedConditions(): void
    {
        OnboardingCondition::query()->updateOrCreate(
            ['key' => 'email_verified'],
            [
                'label' => ['en' => 'Email address is verified'],
                'description' => ['en' => 'The user has confirmed their email address.'],
                'type' => ConditionType::Attribute,
                'filters' => [
                    ['column' => 'email_verified_at', 'operator' => ConditionOperator::IsSet->value, 'value' => null],
                ],
                'minimum' => 1,
                'is_active' => true,
            ],
        );

        OnboardingCondition::query()->updateOrCreate(
            ['key' => 'has_created_order'],
            [
                'label' => ['en' => 'Has created an order'],
                'description' => ['en' => 'The user has at least one order of their own.'],
                'type' => ConditionType::Aggregate,
                'model' => Order::class,
                'subject_column' => 'user_id',
                'filters' => [],
                'minimum' => 1,
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function seedFlow(array $flow, int $order): void
    {
        $steps = $flow['steps'];
        unset($flow['steps']);

        $model = OnboardingFlow::query()->updateOrCreate(
            ['key' => $flow['key']],
            [
                ...$flow,
                'panel_id' => self::PANEL,
                'is_active' => true,
                'is_dismissible' => true,
                'sort_order' => $order,
            ],
        );

        foreach ($steps as $position => $step) {
            OnboardingStep::query()->updateOrCreate(
                ['flow_id' => $model->getKey(), 'key' => $step['key']],
                [
                    'type' => StepType::Task,
                    'completion_mode' => CompletionMode::Manual,
                    'is_required' => true,
                    'is_active' => true,
                    ...$step,
                    'sort_order' => $position,
                ],
            );
        }
    }

    /**
     * Flow definitions, one per area of the admin panel.
     *
     * @return array<int, array<string, mixed>>
     */
    private function flows(): array
    {
        return [
            $this->gettingStarted(),
            $this->catalog(),
            $this->sales(),
            $this->administration(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gettingStarted(): array
    {
        return [
            'key' => 'getting-started',
            'title' => ['en' => 'Getting started'],
            'description' => ['en' => 'Find your way around the admin panel and secure your account.'],
            'icon' => 'heroicon-o-rocket-launch',
            'color' => 'primary',
            'steps' => [
                [
                    'key' => 'welcome',
                    'title' => ['en' => 'Welcome to the shop admin'],
                    'description' => ['en' => 'This checklist walks you through every part of the panel. Tick each step off as you go.'],
                    'icon' => 'heroicon-o-hand-raised',
                ],
                [
                    'key' => 'dashboard-tour',
                    'type' => StepType::Tour,
                    'title' => ['en' => 'Tour the dashboard'],
                    'description' => ['en' => 'See the live numbers the dashboard tracks for you.'],
                    'icon' => 'heroicon-o-chart-bar',
                    'cta_label' => ['en' => 'Start tour'],
                    'cta_route' => 'filament.admin.pages.dashboard',
                    'tour_steps' => [
                        $this->stop('filament.admin.pages.dashboard', 'Revenue and orders', 'Revenue from paid orders, orders placed today and orders still pending.', widget: ShopStats::class),
                        $this->stop('filament.admin.pages.dashboard', 'Order statuses', 'How many orders sit in each status of the order lifecycle.', widget: OrderStatusStats::class),
                        $this->stop('filament.admin.pages.dashboard', 'Coupons', 'Active, expired and used coupons at a glance.', widget: CouponStats::class),
                        $this->stop('filament.admin.pages.dashboard', 'Shipments', 'Shipments in transit and delivered.', widget: ShipmentStats::class),
                    ],
                ],
                [
                    'key' => 'verify-email',
                    'title' => ['en' => 'Verify your email address'],
                    'description' => ['en' => 'Confirm your email so you can receive account and order notifications. Completes on its own once verified.'],
                    'icon' => 'heroicon-o-envelope',
                    'completion_mode' => CompletionMode::Condition,
                    'condition_key' => 'email_verified',
                    'cta_label' => ['en' => 'Open profile'],
                    'cta_route' => 'filament.admin.auth.profile',
                ],
                [
                    'key' => 'secure-account',
                    'title' => ['en' => 'Secure your account'],
                    'description' => ['en' => 'Turn on app or email two-factor authentication from your profile. Completes when you open the profile page.'],
                    'icon' => 'heroicon-o-shield-check',
                    'completion_mode' => CompletionMode::Visit,
                    'visit_url' => '/admin/profile',
                    'cta_label' => ['en' => 'Open profile'],
                    'cta_route' => 'filament.admin.auth.profile',
                ],
                [
                    'key' => 'review-notifications',
                    'title' => ['en' => 'Check your notifications'],
                    'description' => ['en' => 'The bell in the top bar collects database notifications, such as order and system alerts.'],
                    'icon' => 'heroicon-o-bell',
                ],
                [
                    'key' => 'use-global-search',
                    'title' => ['en' => 'Try the global search'],
                    'description' => ['en' => 'Press the search shortcut in the top bar to jump straight to a product, order, customer or coupon.'],
                    'icon' => 'heroicon-o-magnifying-glass',
                    'is_required' => false,
                ],
                [
                    'key' => 'view-progress',
                    'title' => ['en' => 'Review your onboarding progress'],
                    'description' => ['en' => 'The progress page lays out every journey and how far you are in each. Completes when you open it.'],
                    'icon' => 'heroicon-o-map',
                    'completion_mode' => CompletionMode::Visit,
                    'visit_url' => '/admin/onboarding',
                    'cta_label' => ['en' => 'Open progress'],
                    'cta_route' => 'filament.admin.pages.onboarding',
                    'is_required' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        return [
            'key' => 'manage-catalog',
            'title' => ['en' => 'Manage the catalog'],
            'description' => ['en' => 'Organise categories, suppliers and products.'],
            'icon' => 'heroicon-o-squares-2x2',
            'color' => 'info',
            'steps' => [
                [
                    'key' => 'categories',
                    'type' => StepType::Tour,
                    'title' => ['en' => 'Organise categories'],
                    'description' => ['en' => 'Categories group your products. Each category lists its products in a relation manager.'],
                    'icon' => 'heroicon-o-tag',
                    'cta_label' => ['en' => 'Go to categories'],
                    'cta_route' => 'filament.admin.resources.categories.index',
                    'tour_steps' => [
                        $this->stop('filament.admin.resources.categories.index', 'Categories', 'Every category in the shop. Search, sort and open one to edit it.'),
                        $this->stop('filament.admin.resources.categories.create', 'Create a category', 'Give the category a name; its products are managed from its edit page.'),
                    ],
                ],
                [
                    'key' => 'suppliers',
                    'type' => StepType::Tour,
                    'title' => ['en' => 'Add your suppliers'],
                    'description' => ['en' => 'Suppliers are linked to the products they provide.'],
                    'icon' => 'heroicon-o-truck',
                    'cta_label' => ['en' => 'Go to suppliers'],
                    'cta_route' => 'filament.admin.resources.suppliers.index',
                    'tour_steps' => [
                        $this->stop('filament.admin.resources.suppliers.index', 'Suppliers', 'All suppliers, with their contact details.'),
                        $this->stop('filament.admin.resources.suppliers.create', 'Add a supplier', 'Record a new supplier before attaching it to products.'),
                    ],
                ],
                [
                    'key' => 'products',
                    'type' => StepType::Tour,
                    'title' => ['en' => 'Create and price products'],
                    'description' => ['en' => 'Products carry a price in PHP, stock on hand, a category and a supplier.'],
                    'icon' => 'heroicon-o-cube',
                    'cta_label' => ['en' => 'Go to products'],
                    'cta_route' => 'filament.admin.resources.products.index',
                    'tour_steps' => [
                        $this->stop('filament.admin.resources.products.index', 'Products', 'Browse and filter the catalog; open a row to view or edit it.'),
                        $this->stop('filament.admin.resources.products.create', 'New product', 'Set the name, price, stock, category and supplier.'),
                    ],
                ],
                [
                    'key' => 'visit-products',
                    'title' => ['en' => 'Open the product list'],
                    'description' => ['en' => 'Completes automatically when you visit any products page.'],
                    'icon' => 'heroicon-o-eye',
                    'completion_mode' => CompletionMode::Visit,
                    'visit_url' => '/admin/products*',
                    'is_required' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sales(): array
    {
        return [
            'key' => 'manage-sales',
            'title' => ['en' => 'Run orders and fulfilment'],
            'description' => ['en' => 'Handle orders, coupons and shipments from checkout to delivery.'],
            'icon' => 'heroicon-o-shopping-bag',
            'color' => 'success',
            'steps' => [
                [
                    'key' => 'orders',
                    'type' => StepType::Tour,
                    'title' => ['en' => 'Work with orders'],
                    'description' => ['en' => 'Orders move through pending, paid, shipped, delivered or cancelled. Status changes use the status actions on each order.'],
                    'icon' => 'heroicon-o-clipboard-document-list',
                    'cta_label' => ['en' => 'Go to orders'],
                    'cta_route' => 'filament.admin.resources.orders.index',
                    'tour_steps' => [
                        $this->stop('filament.admin.resources.orders.index', 'Orders', 'Every order with its status and total. Use the status actions to move one forward.'),
                        $this->stop('filament.admin.resources.orders.create', 'Create an order', 'Pick the customer and add items; the total is calculated from them.'),
                    ],
                ],
                [
                    'key' => 'create-first-order',
                    'title' => ['en' => 'Create your first order'],
                    'description' => ['en' => 'Completes once an order is recorded against your account.'],
                    'icon' => 'heroicon-o-plus-circle',
                    'completion_mode' => CompletionMode::Condition,
                    'condition_key' => 'has_created_order',
                    'cta_label' => ['en' => 'New order'],
                    'cta_route' => 'filament.admin.resources.orders.create',
                    'is_required' => false,
                ],
                [
                    'key' => 'coupons',
                    'type' => StepType::Tour,
                    'title' => ['en' => 'Set up coupons'],
                    'description' => ['en' => 'Coupons give a discount until they expire or are deactivated.'],
                    'icon' => 'heroicon-o-ticket',
                    'cta_label' => ['en' => 'Go to coupons'],
                    'cta_route' => 'filament.admin.resources.coupons.index',
                    'tour_steps' => [
                        $this->stop('filament.admin.resources.coupons.index', 'Coupons', 'Codes, discounts, expiry dates and whether each is active.'),
                        $this->stop('filament.admin.resources.coupons.create', 'New coupon', 'Choose a code, a discount, an expiry date and activate it.'),
                    ],
                ],
                [
                    'key' => 'shipments',
                    'type' => StepType::Tour,
                    'title' => ['en' => 'Track shipments'],
                    'description' => ['en' => 'A shipment ties an order to a courier and tracking number.'],
                    'icon' => 'heroicon-o-paper-airplane',
                    'cta_label' => ['en' => 'Go to shipments'],
                    'cta_route' => 'filament.admin.resources.shipments.index',
                    'tour_steps' => [
                        $this->stop('filament.admin.resources.shipments.index', 'Shipments', 'Every shipment with its courier, tracking number and ship date.'),
                        $this->stop('filament.admin.resources.shipments.create', 'Record a shipment', 'Link it to an order and enter the courier and tracking number.'),
                    ],
                ],
                [
                    'key' => 'close-the-loop',
                    'title' => ['en' => 'Mark fulfilment training done'],
                    'description' => ['en' => 'Completed by code once your team lead signs you off: Onboarding::for($user)->complete("close-the-loop").'],
                    'icon' => 'heroicon-o-check-badge',
                    'completion_mode' => CompletionMode::Programmatic,
                    'is_required' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function administration(): array
    {
        return [
            'key' => 'administration',
            'title' => ['en' => 'Administer users'],
            'description' => ['en' => 'Manage staff and customers.'],
            'icon' => 'heroicon-o-cog-6-tooth',
            'color' => 'warning',
            'steps' => [
                [
                    'key' => 'users',
                    'type' => StepType::Tour,
                    'title' => ['en' => 'Manage users'],
                    'description' => ['en' => 'Customers and staff are both user records. Roles and permissions control what each can do.'],
                    'icon' => 'heroicon-o-users',
                    'cta_label' => ['en' => 'Go to users'],
                    'cta_route' => 'filament.admin.resources.users.index',
                    'tour_steps' => [
                        $this->stop('filament.admin.resources.users.index', 'Users', 'Search the user list and open a record to view or edit it.'),
                        $this->stop('filament.admin.resources.users.create', 'Add a user', 'Create a user and assign their role.'),
                    ],
                ],
            ],
        ];
    }

    /**
     * One stop of a guided tour.
     *
     * @return array<string, mixed>
     */
    private function stop(string $route, string $title, string $body, ?string $widget = null): array
    {
        return [
            'route' => $route,
            'widget' => $widget,
            'placement' => 'auto',
            'optional' => false,
            'title' => ['en' => $title],
            'body' => ['en' => $body],
        ];
    }
}
