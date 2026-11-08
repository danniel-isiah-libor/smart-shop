<?php

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

test('a new order defaults to the pending status', function () {
    $order = Order::factory()->create();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('an order links its user, items and products', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->for($user)->create();
    $item = OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);

    expect($order->user->is($user))->toBeTrue()
        ->and($user->orders->contains($order))->toBeTrue()
        ->and($order->items->first()->is($item))->toBeTrue()
        ->and($item->order->is($order))->toBeTrue()
        ->and($item->product->is($product))->toBeTrue()
        ->and($product->orderItems->contains($item))->toBeTrue()
        ->and($order->products->first()->pivot->quantity)->toBe(2);
});

test('order items snapshot the price so later product changes do not alter them', function () {
    $product = Product::factory()->create(['price' => 10]);
    $item = OrderItem::factory()->for($product)->create([
        'unit_price' => $product->price,
        'quantity' => 3,
        'line_total' => 30,
    ]);

    $product->update(['price' => 99]);

    expect($item->fresh()->unit_price)->toBe('10.00')
        ->and($item->fresh()->line_total)->toBe('30.00');
});

test('deleting an order deletes its items', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create();

    $order->delete();

    expect(OrderItem::count())->toBe(0);
});

test('a product that was ordered cannot be deleted', function () {
    $item = OrderItem::factory()->create();

    $item->product->delete();
})->throws(QueryException::class);

test('a user with orders cannot be deleted', function () {
    $order = Order::factory()->create();

    $order->user->delete();
})->throws(QueryException::class);

test('the same product cannot appear twice on one order', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();

    OrderItem::factory()->for($order)->for($product)->create();
    OrderItem::factory()->for($order)->for($product)->create();
})->throws(QueryException::class);

test('order numbers must be unique', function () {
    Order::factory()->create(['order_number' => 'ORD-000001']);
    Order::factory()->create(['order_number' => 'ORD-000001']);
})->throws(QueryException::class);

test('every order status has a matching factory state', function (OrderStatus $status) {
    $order = Order::factory()->{$status->value}()->create();

    expect($order->fresh()->status)->toBe($status);
})->with(OrderStatus::cases());

test('an order item cannot reference a product that does not exist', function () {
    OrderItem::factory()->create(['product_id' => 999999]);
})->throws(QueryException::class);

test('an order item cannot reference an order that does not exist', function () {
    OrderItem::factory()->create(['order_id' => 999999]);
})->throws(QueryException::class);

test('an order cannot reference a user that does not exist', function () {
    Order::factory()->create(['user_id' => 999999]);
})->throws(QueryException::class);

test('an order with an unknown status value cannot be read', function () {
    $order = Order::factory()->create();
    DB::table('orders')->where('id', $order->id)->update(['status' => 'refunded']);

    $order->fresh()->status;
})->throws(ValueError::class);

test('an order without items has no items or products', function () {
    $order = Order::factory()->create();

    expect($order->items)->toBeEmpty()
        ->and($order->products)->toBeEmpty();
});

test('deleting an order keeps the products that were ordered', function () {
    $item = OrderItem::factory()->create();
    $product = $item->product;

    $item->order->delete();

    expect($product->fresh())->not->toBeNull();
});

test('order and order item tables have indexes on their lookup columns', function () {
    $orderIndexes = collect(Schema::getIndexes('orders'))->pluck('columns');
    $itemIndexes = collect(Schema::getIndexes('order_items'))->pluck('columns');

    expect($orderIndexes->contains(['user_id', 'status']))->toBeTrue()
        ->and($orderIndexes->contains(['status']))->toBeTrue()
        ->and($orderIndexes->contains(['created_at']))->toBeTrue()
        ->and($itemIndexes->contains(['product_id']))->toBeTrue();
});

test('an order can move from pending to paid', function () {
    $order = Order::factory()->pending()->create();

    $order->moveTo(OrderStatus::Paid);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('an order refuses an invalid status move', function () {
    $order = Order::factory()->shipped()->create();

    expect(fn () => $order->moveTo(OrderStatus::Pending))->toThrow(DomainException::class);
    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

test('moving an order notifies the customer with a link', function () {
    Notification::fake();
    $order = Order::factory()->pending()->create();

    $order->moveTo(OrderStatus::Paid);

    Notification::assertSentTo($order->user, OrderStatusUpdated::class, function ($notification) use ($order) {
        $mail = $notification->toMail($order->user);

        return $mail->actionUrl === route('dashboard')
            && str_contains($mail->subject, $order->order_number)
            && str_contains($mail->subject, 'Paid');
    });
});

test('a cancelled order gets a friendlier message', function () {
    Notification::fake();
    $order = Order::factory()->pending()->create();

    $order->moveTo(OrderStatus::Cancelled);

    Notification::assertSentTo($order->user, OrderStatusUpdated::class, function ($notification) use ($order) {
        $lines = implode(' ', $notification->toMail($order->user)->introLines);

        return str_contains($lines, "We're sorry") && str_contains($lines, $order->order_number);
    });
});

test('an invalid status move sends no notification', function () {
    Notification::fake();
    $order = Order::factory()->shipped()->create();

    expect(fn () => $order->moveTo(OrderStatus::Pending))->toThrow(DomainException::class);

    Notification::assertNothingSent();
});

test('an order links its coupon and the link is cleared when the coupon is deleted', function () {
    $coupon = Coupon::factory()->create(['code' => 'LINKED']);
    $order = Order::factory()->withCoupon($coupon)->create();

    expect($order->coupon->is($coupon))->toBeTrue();

    $coupon->delete();

    expect($order->fresh()->coupon_id)->toBeNull()
        ->and($order->fresh()->coupon_code)->toBe('LINKED');
});

test('an order cannot reference a coupon that does not exist', function () {
    Order::factory()->create(['coupon_id' => 999999]);
})->throws(QueryException::class);

test('a failed payment move leaves the order pending and notifies nobody', function () {
    Notification::fake();
    $order = Order::factory()->pending()->create();
    $product = Product::factory()->create(['stock' => 0]);
    OrderItem::factory()->for($order)->for($product)->create(['quantity' => 1]);

    expect(fn () => $order->moveTo(OrderStatus::Paid))->toThrow(DomainException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
    Notification::assertNothingSent();
});
