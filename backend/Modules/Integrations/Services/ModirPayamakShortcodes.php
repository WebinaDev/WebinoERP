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
            ['key' => 'product_url', 'label' => 'لینک محصول', 'scope' => 'stock'],
            ['key' => 'qty', 'label' => 'موجودی', 'scope' => 'stock'],
            ['key' => 'stock_quantity', 'label' => 'موجودی انبار', 'scope' => 'stock'],
        ];
    }
}
