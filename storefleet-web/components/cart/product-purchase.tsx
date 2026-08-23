"use client";

import {
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import {
  type CartProduct,
  useCart,
} from "@/components/cart/cart-provider";


export default function ProductPurchase({
  product,
}: {
  product: CartProduct;
}) {
  const [
    quantity,
    setQuantity,
  ] = useState(1);

  const [
    added,
    setAdded,
  ] = useState(false);

  const {
    addItem,
  } = useCart();

  const router =
    useRouter();


  const outOfStock =
    product.stock <= 0;


  function decrease() {
    setQuantity(
      (current) =>
        Math.max(
          1,
          current - 1
        )
    );
  }


  function increase() {
    setQuantity(
      (current) =>
        Math.min(
          product.stock,
          current + 1
        )
    );
  }


  function handleAddToCart() {
    if (outOfStock) {
      return;
    }

    addItem(
      product,
      quantity
    );

    setAdded(true);

    window.setTimeout(
      () => {
        setAdded(false);
      },
      1800
    );
  }


  function handleBuyNow() {
    if (outOfStock) {
      return;
    }

    addItem(
      product,
      quantity
    );

    router.push(
      "/cart"
    );
  }


  return (
    <div className="mt-8 rounded-3xl border border-zinc-200 bg-zinc-50 p-5 sm:p-6">

      <div className="flex flex-col gap-3 sm:flex-row">

        {/* Quantity */}

        <div className="flex min-h-13 items-center justify-between rounded-full border border-zinc-300 bg-white sm:w-36">

          <button
            type="button"
            onClick={decrease}
            disabled={
              quantity <= 1 ||
              outOfStock
            }
            className="flex h-full w-12 items-center justify-center text-xl font-bold transition hover:text-violet-600 disabled:cursor-not-allowed disabled:text-zinc-300"
          >
            −
          </button>


          <span className="font-black">
            {quantity}
          </span>


          <button
            type="button"
            onClick={increase}
            disabled={
              quantity >=
                product.stock ||
              outOfStock
            }
            className="flex h-full w-12 items-center justify-center text-xl font-bold transition hover:text-violet-600 disabled:cursor-not-allowed disabled:text-zinc-300"
          >
            +
          </button>

        </div>


        {/* Add */}

        <button
          type="button"
          onClick={
            handleAddToCart
          }
          disabled={
            outOfStock
          }
          className={`min-h-13 flex-1 rounded-full px-8 font-bold text-white transition disabled:cursor-not-allowed disabled:bg-zinc-300 ${
            added
              ? "bg-emerald-600"
              : "bg-violet-600 hover:bg-violet-700"
          }`}
        >
          {outOfStock
            ? "Out of Stock"
            : added
              ? "Added to Cart ✓"
              : "Add to Cart"}
        </button>

      </div>


      <button
        type="button"
        onClick={
          handleBuyNow
        }
        disabled={
          outOfStock
        }
        className="mt-3 min-h-13 w-full rounded-full bg-zinc-950 px-8 font-bold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:bg-zinc-300"
      >
        Buy Now
      </button>


      <p className="mt-4 text-center text-xs leading-5 text-zinc-500">
        Secure online checkout through
        StoreFleet.
      </p>

    </div>
  );
}