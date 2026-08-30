"use client";

import Link from "next/link";

import {
  type CartItem,
  useCart,
} from "@/components/cart/cart-provider";


export default function CheckoutOrderSummary() {
  const {
    items,
    subtotal,
    hydrated,
  } = useCart();


  /*
  |--------------------------------------------------------------------------
  | Hydration
  |--------------------------------------------------------------------------
  */

  if (!hydrated) {
    return (
      <aside>

        <div className="sticky top-24 rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">

          <div className="h-6 w-36 animate-pulse rounded bg-zinc-200" />

          <div className="mt-6 space-y-4">

            <div className="h-16 animate-pulse rounded-2xl bg-zinc-100" />

            <div className="h-16 animate-pulse rounded-2xl bg-zinc-100" />

          </div>

        </div>

      </aside>
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Empty Cart
  |--------------------------------------------------------------------------
  */

  if (items.length === 0) {
    return (
      <aside>

        <div className="sticky top-24 rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">

          <h2 className="text-lg font-bold text-zinc-950">
            Order Summary
          </h2>


          <div className="mt-6 rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 px-5 py-8 text-center">

            <p className="font-bold text-zinc-900">
              Your cart is empty.
            </p>


            <p className="mt-2 text-sm leading-6 text-zinc-500">
              Add products before continuing with checkout.
            </p>


            <Link
              href="/shop"
              className="mt-5 inline-flex min-h-11 items-center justify-center rounded-full bg-violet-600 px-5 text-sm font-bold text-white"
            >
              Shop products
            </Link>

          </div>

        </div>

      </aside>
    );
  }


  const merchantGroups =
    groupByMerchant(
      items
    );


  return (
    <aside>

      <div className="sticky top-24 rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">

        <div className="flex items-center justify-between gap-4">

          <h2 className="text-lg font-bold text-zinc-950">
            Order Summary
          </h2>


          <span className="text-xs font-semibold text-zinc-400">
            {items.length}{" "}
            {items.length === 1
              ? "item"
              : "items"}
          </span>

        </div>


        {/* Merchant Groups */}

        <div className="mt-6 space-y-5">

          {merchantGroups.map(
            (group) => (
              <section
                key={
                  group.store.slug
                }
              >

                <div className="mb-3 flex items-center justify-between gap-3">

                  <Link
                    href={`/stores/${group.store.slug}`}
                    className="min-w-0 truncate text-xs font-black uppercase tracking-[0.08em] text-zinc-500 transition hover:text-violet-600"
                  >
                    {group.store.name}
                  </Link>


                  <span className="shrink-0 text-xs font-bold text-zinc-500">
                    {formatPrice(
                      group.subtotal
                    )}
                  </span>

                </div>


                <div className="space-y-3">

                  {group.items.map(
                    (item) => (
                      <CheckoutCartItem
                        key={
                          item.id
                        }
                        item={
                          item
                        }
                      />
                    )
                  )}

                </div>

              </section>
            )
          )}

        </div>


        <div className="my-6 border-t border-zinc-200" />


        {/* Totals */}

        <div className="space-y-4">

          <SummaryRow
            label="Subtotal"
            value={
              formatPrice(
                subtotal
              )
            }
          />


          <SummaryRow
            label="Delivery"
            value="Calculated next"
          />

        </div>


        <div className="my-6 border-t border-zinc-200" />


        <div className="flex items-end justify-between gap-5">

          <span className="font-bold text-zinc-950">
            Current total
          </span>


          <strong className="text-2xl font-black text-violet-700">
            {formatPrice(
              subtotal
            )}
          </strong>

        </div>


        <p className="mt-3 text-xs leading-5 text-zinc-500">
          Delivery is not included yet. Final prices, product availability and branch stock must be validated again before the order is created.
        </p>


        {/* Place Order stays disabled until delivery + payment are implemented */}

        <button
          type="button"
          disabled
          className="mt-6 flex w-full cursor-not-allowed items-center justify-center rounded-xl bg-zinc-950 px-5 py-3.5 text-sm font-semibold text-white opacity-40"
        >
          Place Order
        </button>


        <p className="mt-3 text-center text-xs leading-5 text-zinc-500">
          Order placement will be enabled when delivery and payment integration is complete.
        </p>


        <Link
          href="/cart"
          className="mt-5 flex items-center justify-center text-sm font-semibold text-zinc-600 transition hover:text-violet-600"
        >
          ← Return to cart
        </Link>

      </div>

    </aside>
  );
}


/*
|--------------------------------------------------------------------------
| Checkout Cart Item
|--------------------------------------------------------------------------
*/

function CheckoutCartItem({
  item,
}: {
  item: CartItem;
}) {
  return (
    <article className="grid grid-cols-[56px_1fr_auto] gap-3">

      <Link
        href={`/shop/${item.slug}`}
        className="aspect-square overflow-hidden rounded-xl bg-zinc-100"
      >

        {item.image ? (
          <img
            src={
              item.image
            }
            alt={
              item.name
            }
            className="h-full w-full object-cover"
          />
        ) : (
          <div className="flex h-full items-center justify-center text-[10px] font-bold text-zinc-400">
            No image
          </div>
        )}

      </Link>


      <div className="min-w-0">

        <Link
          href={`/shop/${item.slug}`}
          className="line-clamp-2 text-sm font-bold leading-5 text-zinc-900 transition hover:text-violet-600"
        >
          {item.name}
        </Link>


        <p className="mt-1 text-xs text-zinc-500">
          {formatPrice(
            item.price
          )}{" "}
          ×{" "}
          {item.quantity}
        </p>

      </div>


      <strong className="text-right text-sm text-zinc-900">
        {formatPrice(
          item.price *
            item.quantity
        )}
      </strong>

    </article>
  );
}


/*
|--------------------------------------------------------------------------
| Merchant Groups
|--------------------------------------------------------------------------
*/

function groupByMerchant(
  items: CartItem[]
) {
  const groups =
    new Map<
      string,
      {
        store:
          CartItem["store"];

        items:
          CartItem[];

        subtotal:
          number;
      }
    >();


  items.forEach(
    (item) => {
      const key =
        item.store.slug;


      const existing =
        groups.get(
          key
        );


      if (existing) {
        existing.items.push(
          item
        );

        existing.subtotal +=
          item.price *
          item.quantity;

        return;
      }


      groups.set(
        key,
        {
          store:
            item.store,

          items: [
            item,
          ],

          subtotal:
            item.price *
            item.quantity,
        }
      );
    }
  );


  return Array.from(
    groups.values()
  );
}


/*
|--------------------------------------------------------------------------
| Summary Row
|--------------------------------------------------------------------------
*/

function SummaryRow({
  label,
  value,
}: {
  label: string;
  value: string;
}) {
  return (
    <div className="flex items-center justify-between gap-5 text-sm">

      <span className="text-zinc-500">
        {label}
      </span>


      <span className="text-right font-semibold text-zinc-900">
        {value}
      </span>

    </div>
  );
}


/*
|--------------------------------------------------------------------------
| Utilities
|--------------------------------------------------------------------------
*/

function formatPrice(
  value: number
) {
  return new Intl.NumberFormat(
    "en-PH",
    {
      style:
        "currency",

      currency:
        "PHP",

      minimumFractionDigits:
        0,
    }
  ).format(
    value
  );
}
