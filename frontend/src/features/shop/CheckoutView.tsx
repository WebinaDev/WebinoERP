"use client"

/* /checkout: the stepped checkout (address, delivery time, shipping, payment) shared with the builder widget. */

import { StorefrontSteppedCheckout } from "@/builder/storefront/checkout-steps"

export function CheckoutView() {
  return <StorefrontSteppedCheckout />
}
