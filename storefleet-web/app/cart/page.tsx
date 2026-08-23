"use client";

import Link from "next/link";

import {
  type CartItem,
  useCart,
} from "@/components/cart/cart-provider";


export default function CartPage() {
  const {
    items,
    removeItem,
    updateQuantity,
    clearCart,
    subtotal,
    hydrated,
  } = useCart();


  if (!hydrated) {
    return (
      <main className="min-h-[60vh] bg-zinc-50">

        <div className="mx-auto max-w-7xl px-5 py-16 sm:px-6 lg:px-8">

          <div className="h-8 w-40 animate-pulse rounded bg-zinc-200" />

          <div className="mt-8 h-60 animate-pulse rounded-3xl bg-zinc-200" />

        </div>

      </main>
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Empty Cart
  |--------------------------------------------------------------------------
  */

  if (items.length === 0) {
    return (
      <main className="min-h-[70vh] bg-zinc-50">

        <div className="mx-auto flex max-w-2xl flex-col items-center px-5 py-24 text-center sm:px-6">

          <div className="flex h-20 w-20 items-center justify-center rounded-full bg-violet-100 text-3xl">
            🛒
          </div>


          <span className="mt-8 text-xs font-black uppercase tracking-[0.15em] text-violet-600">
            Your Cart
          </span>


          <h1 className="mt-4 text-4xl font-black tracking-[-0.05em] sm:text-5xl">
            Your cart is empty.
          </h1>


          <p className="mt-5 max-w-md leading-7 text-zinc-500">
            Browse StoreFleet and add
            products from local merchants
            to your cart.
          </p>


          <Link
            href="/shop"
            className="mt-8 inline-flex min-h-12 items-center justify-center rounded-full bg-violet-600 px-7 text-sm font-bold text-white transition hover:bg-violet-700"
          >
            Start Shopping
          </Link>

        </div>

      </main>
    );
  }


  const storeGroups =
    groupByStore(items);


  return (
    <main className="bg-zinc-50">

      {/* Header */}

      <section className="border-b border-zinc-200 bg-white">

        <div className="mx-auto w-full max-w-7xl px-5 py-12 sm:px-6 lg:px-8">

          <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
            StoreFleet Cart
          </span>


          <div className="mt-4 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">

            <div>

              <h1 className="text-4xl font-black tracking-[-0.055em] sm:text-5xl">
                Your cart.
              </h1>


              <p className="mt-3 text-zinc-500">
                {items.length}{" "}
                {items.length === 1
                  ? "product"
                  : "products"}{" "}
                from{" "}
                {storeGroups.length}{" "}
                {storeGroups.length === 1
                  ? "merchant"
                  : "merchants"}
              </p>

            </div>


            <button
              type="button"
              onClick={clearCart}
              className="text-left text-sm font-bold text-red-600 transition hover:text-red-700"
            >
              Clear cart
            </button>

          </div>

        </div>

      </section>


      {/* Content */}

      <section className="py-10 sm:py-14">

        <div className="mx-auto grid w-full max-w-7xl gap-8 px-5 sm:px-6 lg:grid-cols-[1fr_380px] lg:px-8">

          {/* Merchant Groups */}

          <div className="space-y-6">

            {storeGroups.map(
              (group) => (

                <MerchantCartGroup
                  key={
                    group.store.slug
                  }
                  store={
                    group.store
                  }
                  items={
                    group.items
                  }
                  updateQuantity={
                    updateQuantity
                  }
                  removeItem={
                    removeItem
                  }
                />

              )
            )}

          </div>


          {/* Summary */}

          <aside>

            <div className="sticky top-28 rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">

              <h2 className="text-xl font-black">
                Order summary
              </h2>


              <div className="mt-6 space-y-4">

                <SummaryRow
                  label="Products"
                  value={formatPrice(
                    subtotal
                  )}
                />


                <SummaryRow
                  label="Delivery"
                  value="Calculated at checkout"
                />

              </div>


              <div className="mt-6 border-t border-zinc-200 pt-6">

                <div className="flex items-end justify-between gap-4">

                  <span className="font-bold">
                    Subtotal
                  </span>


                  <strong className="text-2xl font-black text-violet-700">
                    {formatPrice(
                      subtotal
                    )}
                  </strong>

                </div>


                <p className="mt-3 text-xs leading-5 text-zinc-500">
                  Delivery fees and final
                  totals will be calculated
                  during checkout.
                </p>

              </div>


              <button
                type="button"
                className="mt-7 min-h-13 w-full rounded-full bg-violet-600 px-6 font-bold text-white transition hover:bg-violet-700"
              >
                Proceed to Checkout
              </button>


              <Link
                href="/shop"
                className="mt-3 flex min-h-11 w-full items-center justify-center rounded-full text-sm font-bold text-zinc-500 transition hover:text-violet-600"
              >
                Continue Shopping
              </Link>

            </div>

          </aside>

        </div>

      </section>

    </main>
  );
}


/*
|--------------------------------------------------------------------------
| Merchant Cart Group
|--------------------------------------------------------------------------
*/

function MerchantCartGroup({
  store,
  items,
  updateQuantity,
  removeItem,
}: {
  store: CartItem["store"];

  items: CartItem[];

  updateQuantity: (
    id: number,
    quantity: number
  ) => void;

  removeItem: (
    id: number
  ) => void;
}) {
  const storeSubtotal =
    items.reduce(
      (
        total,
        item
      ) =>
        total +
        item.price *
          item.quantity,
      0
    );


  return (
    <section className="overflow-hidden rounded-3xl border border-zinc-200 bg-white">

      {/* Merchant */}

      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-zinc-200 px-5 py-4 sm:px-6">

        <Link
          href={`/stores/${store.slug}`}
          className="flex min-w-0 items-center gap-3"
        >

          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-black text-violet-700">
            {getInitials(
              store.name
            )}
          </div>


          <div className="min-w-0">

            <div className="flex items-center gap-2">

              <strong className="truncate">
                {store.name}
              </strong>


              {store.verified && (
                <VerifiedBadge />
              )}

            </div>


            <span className="text-xs text-zinc-500">
              {store.verified
                ? "StoreFleet Verified"
                : "StoreFleet Merchant"}
            </span>

          </div>

        </Link>


        <Link
          href={`/stores/${store.slug}`}
          className="text-xs font-bold text-violet-600"
        >
          Visit Store →
        </Link>

      </div>


      {/* Products */}

      <div className="divide-y divide-zinc-100">

        {items.map(
          (item) => (

            <CartProductRow
              key={item.id}
              item={item}
              updateQuantity={
                updateQuantity
              }
              removeItem={
                removeItem
              }
            />

          )
        )}

      </div>


      {/* Merchant subtotal */}

      <div className="flex items-center justify-between gap-5 border-t border-zinc-200 bg-zinc-50 px-5 py-4 text-sm sm:px-6">

        <span className="text-zinc-500">
          Merchant subtotal
        </span>


        <strong>
          {formatPrice(
            storeSubtotal
          )}
        </strong>

      </div>

    </section>
  );
}


/*
|--------------------------------------------------------------------------
| Cart Product
|--------------------------------------------------------------------------
*/

function CartProductRow({
  item,
  updateQuantity,
  removeItem,
}: {
  item: CartItem;

  updateQuantity: (
    id: number,
    quantity: number
  ) => void;

  removeItem: (
    id: number
  ) => void;
}) {
  return (
    <article className="grid gap-4 p-5 sm:grid-cols-[110px_1fr_auto] sm:p-6">

      {/* Image */}

      <Link
        href={`/shop/${item.slug}`}
        className="aspect-square overflow-hidden rounded-2xl bg-zinc-100"
      >

        <img
          src={item.image}
          alt={item.name}
          className="h-full w-full object-cover"
        />

      </Link>


      {/* Information */}

      <div className="min-w-0">

        <Link
          href={`/shop/${item.slug}`}
          className="font-black transition hover:text-violet-600"
        >
          {item.name}
        </Link>


        <div className="mt-2 flex flex-wrap items-baseline gap-2">

          <strong className="text-lg text-violet-700">
            {formatPrice(
              item.price
            )}
          </strong>


          {item.regularPrice >
            item.price && (

            <span className="text-xs text-zinc-400 line-through">
              {formatPrice(
                item.regularPrice
              )}
            </span>

          )}

        </div>


        <div className="mt-4 flex flex-wrap items-center gap-4">

          {/* Quantity */}

          <div className="flex h-10 items-center rounded-full border border-zinc-300">

            <button
              type="button"
              onClick={() =>
                updateQuantity(
                  item.id,
                  item.quantity - 1
                )
              }
              className="flex h-full w-10 items-center justify-center font-bold"
            >
              −
            </button>


            <span className="min-w-8 text-center text-sm font-black">
              {item.quantity}
            </span>


            <button
              type="button"
              onClick={() =>
                updateQuantity(
                  item.id,
                  item.quantity + 1
                )
              }
              disabled={
                item.quantity >=
                item.stock
              }
              className="flex h-full w-10 items-center justify-center font-bold disabled:cursor-not-allowed disabled:text-zinc-300"
            >
              +
            </button>

          </div>


          <button
            type="button"
            onClick={() =>
              removeItem(
                item.id
              )
            }
            className="text-xs font-bold text-red-600"
          >
            Remove
          </button>

        </div>

      </div>


      {/* Total */}

      <div className="sm:text-right">

        <span className="text-xs text-zinc-400">
          Item total
        </span>


        <strong className="mt-1 block text-lg">
          {formatPrice(
            item.price *
              item.quantity
          )}
        </strong>

      </div>

    </article>
  );
}


/*
|--------------------------------------------------------------------------
| Summary
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
    <div className="flex items-start justify-between gap-5 text-sm">

      <span className="text-zinc-500">
        {label}
      </span>


      <strong className="text-right">
        {value}
      </strong>

    </div>
  );
}


/*
|--------------------------------------------------------------------------
| Verified
|--------------------------------------------------------------------------
*/

function VerifiedBadge() {
  return (
    <span
      title="StoreFleet Verified"
      className="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-violet-600 text-[9px] font-black text-white"
    >
      ✓
    </span>
  );
}


/*
|--------------------------------------------------------------------------
| Group Products by Merchant
|--------------------------------------------------------------------------
*/

function groupByStore(
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
      }
    >();


  for (
    const item of items
  ) {
    const existing =
      groups.get(
        item.store.slug
      );


    if (existing) {
      existing.items.push(
        item
      );
    } else {
      groups.set(
        item.store.slug,
        {
          store:
            item.store,

          items: [
            item,
          ],
        }
      );
    }
  }


  return Array.from(
    groups.values()
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
      style: "currency",
      currency: "PHP",
      minimumFractionDigits: 0,
    }
  ).format(value);
}


function getInitials(
  value: string
) {
  return value
    .split(" ")
    .slice(0, 2)
    .map(
      (word) =>
        word.charAt(0)
    )
    .join("")
    .toUpperCase();
}