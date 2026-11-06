<?php

namespace Modules\Integrations\Services;

/**
 * Site fields a shop can bind to an IPPanel pattern variable.
 *
 * @phpstan-type Shortcode array{key: string, label: string, scope: string}
 */
class ModirPayamakShortcodes
{
    /**
     * @return list<array{key: string, label: string, scope: string}>
     */
    public static function all(): array
    {
        return [
            ['key' => 'mobile', 'label' => 'شماره موبایل مشتری', 'scope' => 'order'],
            ['key' => 'customer_phone', 'label' => 'شماره تلفن مشتری', 'scope' => 'order'],
            ['key' => 'customer_email', 'label' => 'ایمیل مشتری', 'scope' => 'order'],
            ['key' => 'status', 'label' => 'وضعیت سفارش', 'scope' => 'order'],
            ['key' => 'status_label', 'label' => 'برچسب وضعیت سفارش', 'scope' => 'order'],
            ['key' => 'items', 'label' => 'محصولات سفارش', 'scope' => 'order'],
            ['key' => 'all_items', 'label' => 'محصولات سفارش', 'scope' => 'order'],
            ['key' => 'all_items_full', 'label' => 'محصولات سفارش با نام کامل متغیر', 'scope' => 'order'],
            ['key' => 'items_qty', 'label' => 'محصولات سفارش بهمراه تعداد', 'scope' => 'order'],
            ['key' => 'count_items', 'label' => 'تعداد محصولات سفارش', 'scope' => 'order'],
            ['key' => 'total', 'label' => 'مبلغ سفارش', 'scope' => 'order'],
            ['key' => 'price', 'label' => 'مبلغ سفارش', 'scope' => 'order'],
            ['key' => 'total_amount', 'label' => 'مبلغ سفارش بدون واحد', 'scope' => 'order'],
            ['key' => 'price_amount', 'label' => 'مبلغ سفارش بدون واحد', 'scope' => 'order'],
            ['key' => 'order_id', 'label' => 'شماره سفارش اصلی', 'scope' => 'order'],
            ['key' => 'order_number', 'label' => 'شماره سفارش', 'scope' => 'order'],
            ['key' => 'transaction_id', 'label' => 'شماره تراکنش', 'scope' => 'order'],
            ['key' => 'order_date', 'label' => 'تاریخ سفارش', 'scope' => 'order'],
            ['key' => 'description', 'label' => 'توضیحات مشتری', 'scope' => 'order'],
            ['key' => 'payment_method', 'label' => 'روش پرداخت', 'scope' => 'order'],
            ['key' => 'shipping_method', 'label' => 'روش ارسال', 'scope' => 'order'],
            ['key' => 'payment_url', 'label' => 'لینک پرداخت', 'scope' => 'order'],
            ['key' => 'customer_name', 'label' => 'نام مشتری', 'scope' => 'order'],
            ['key' => 'b_first_name', 'label' => 'نام مشتری', 'scope' => 'order'],
            ['key' => 'b_last_name', 'label' => 'نام خانوادگی مشتری', 'scope' => 'order'],
            ['key' => 'b_city', 'label' => 'شهر', 'scope' => 'order'],
            ['key' => 'b_address_1', 'label' => 'آدرس صورتحساب', 'scope' => 'order'],
            ['key' => 'b_postcode', 'label' => 'کد پستی', 'scope' => 'order'],
            ['key' => 'tracking', 'label' => 'کد پیگیری', 'scope' => 'order'],
            ['key' => 'tracking_code', 'label' => 'کد رهگیری', 'scope' => 'order'],
            ['key' => 'tracking_url', 'label' => 'لینک رهگیری', 'scope' => 'order'],
            ['key' => 'barcode', 'label' => 'بارکد پستی', 'scope' => 'order'],
            ['key' => 'coupon', 'label' => 'کد تخفیف اختصاصی', 'scope' => 'order'],
            ['key' => 'coupon_code', 'label' => 'کد تخفیف اختصاصی', 'scope' => 'order'],
            ['key' => 'coupon_amount', 'label' => 'مقدار تخفیف', 'scope' => 'recovery'],
            ['key' => 'coupon_expires', 'label' => 'تاریخ انقضای کدتخفیف', 'scope' => 'recovery'],
            ['key' => 'coupon_discount_text', 'label' => 'متن تخفیف', 'scope' => 'recovery'],
            ['key' => 'site_url', 'label' => 'آدرس سایت', 'scope' => 'all'],
            ['key' => 'code', 'label' => 'کد یکبار مصرف', 'scope' => 'otp'],
            ['key' => 'product_name', 'label' => 'نام محصول', 'scope' => 'stock'],
            ['key' => 'product_title', 'label' => 'نام محصول', 'scope' => 'stock'],
            ['key' => 'product_code', 'label' => 'کد محصول', 'scope' => 'stock'],
            ['key' => 'product_url', 'label' => 'لینک محصول', 'scope' => 'stock'],
            ['key' => 'qty', 'label' => 'موجودی', 'scope' => 'stock'],
            ['key' => 'stock_quantity', 'label' => 'موجودی انبار', 'scope' => 'stock'],
            ['key' => 'cancel_reason', 'label' => 'دلیل لغو', 'scope' => 'order'],
        ];
    }

    /**
     * IPPanel variable name → site shortcode expression.
     *
     * @return array<string, string>
     */
    public static function expressions(): array
    {
        return [
            'otp' => '{code}',
            'code' => '{code}',
            'name' => '{customer_name}',
            'orderid' => '{order_number}',
            'order_id' => '{order_number}',
            'total' => '{total}',
            'amount' => '{total_amount}',
            'status' => '{status_label}',
            'post_tracking_code' => '{tracking_code}',
            'tracking_code' => '{tracking_code}',
            'post_tracking_url' => '{tracking_url}',
            'tracking_url' => '{tracking_url}',
            'product_name' => '{product_name}',
            'product_title' => '{product_name}',
            'product_code' => '{product_code}',
            'stock' => '{stock_quantity}',
            'qty' => '{stock_quantity}',
            'discount_code' => '{coupon_code}',
            'discount_amount' => '{coupon_discount_text}',
            'discount_percent' => '{coupon_discount_text}',
            'expire_date' => '{coupon_expires}',
            'expire_time' => '{coupon_expires}',
            'cancel_reason' => '{cancel_reason}',
            'mobile' => '{customer_phone}',
            'phone' => '{customer_phone}',
            'shipment' => '{shipping_method}',
        ];
    }

    public static function expression(string $varName): string
    {
        return self::expressions()[$varName] ?? '{'.$varName.'}';
    }

    /**
     * Fill pattern variable names from the canonical snapshot fields.
     *
     * @param  array<string, mixed>  $vars
     * @return array<string, string>
     */
    public static function expand(array $vars): array
    {
        $flat = [];
        foreach ($vars as $key => $value) {
            if (is_scalar($value)) {
                $flat[(string) $key] = (string) $value;
            }
        }
        $sources = [
            'otp' => ['code'],
            'name' => ['customer_name', 'b_first_name'],
            'orderid' => ['order_number', 'number', 'order_id'],
            'order_id' => ['order_number', 'number'],
            'amount' => ['total_amount', 'price_amount', 'total'],
            'status' => ['status_label', 'status'],
            'post_tracking_code' => ['tracking_code', 'tracking', 'barcode'],
            'post_tracking_url' => ['tracking_url'],
            'tracking_code' => ['tracking', 'barcode', 'post_tracking_code'],
            'product_title' => ['product_name'],
            'product_code' => ['sku', 'product_sku'],
            'stock' => ['stock_quantity', 'qty'],
            'discount_code' => ['coupon_code', 'coupon'],
            'discount_amount' => ['coupon_discount_text', 'coupon_amount'],
            'discount_percent' => ['coupon_discount_text', 'coupon_amount'],
            'expire_date' => ['coupon_expires'],
            'expire_time' => ['coupon_expires'],
            'shipment' => ['shipping_method'],
            'mobile' => ['customer_phone', 'phone'],
        ];
        foreach ($sources as $alias => $keys) {
            $force = $alias === 'order_id';
            if (! $force && trim($flat[$alias] ?? '') !== '') {
                continue;
            }
            foreach ($keys as $key) {
                if (trim($flat[$key] ?? '') === '') {
                    continue;
                }
                $flat[$alias] = $flat[$key];
                break;
            }
        }

        return $flat;
    }
}
