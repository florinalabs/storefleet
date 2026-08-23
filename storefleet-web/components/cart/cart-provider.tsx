"use client";

import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";


export type CartStore = {
  id: number;
  name: string;
  slug: string;
  verified: boolean;
};


export type CartProduct = {
  id: number;
  slug: string;
  name: string;

  price: number;
  regularPrice: number;

  image: string;
  stock: number;

  store: CartStore;
};


export type CartItem =
  CartProduct & {
    quantity: number;
  };


type CartContextValue = {
  items: CartItem[];

  addItem: (
    product: CartProduct,
    quantity?: number
  ) => void;

  removeItem: (
    productId: number
  ) => void;

  updateQuantity: (
    productId: number,
    quantity: number
  ) => void;

  clearCart: () => void;

  itemCount: number;
  subtotal: number;

  hydrated: boolean;
};


const CartContext =
  createContext<CartContextValue | null>(
    null
  );


const STORAGE_KEY =
  "storefleet_cart_v1";


export function CartProvider({
  children,
}: {
  children: React.ReactNode;
}) {
  const [
    items,
    setItems,
  ] = useState<CartItem[]>([]);

  const [
    hydrated,
    setHydrated,
  ] = useState(false);


  /*
  |--------------------------------------------------------------------------
  | Load Cart
  |--------------------------------------------------------------------------
  */

  useEffect(() => {
    try {
      const stored =
        window.localStorage.getItem(
          STORAGE_KEY
        );

      if (stored) {
        const parsed =
          JSON.parse(stored);

        if (Array.isArray(parsed)) {
          setItems(parsed);
        }
      }
    } catch (error) {
      console.error(
        "Could not load StoreFleet cart:",
        error
      );
    }

    setHydrated(true);
  }, []);


  /*
  |--------------------------------------------------------------------------
  | Save Cart
  |--------------------------------------------------------------------------
  */

  useEffect(() => {
    if (!hydrated) {
      return;
    }

    try {
      window.localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify(items)
      );
    } catch (error) {
      console.error(
        "Could not save StoreFleet cart:",
        error
      );
    }
  }, [
    items,
    hydrated,
  ]);


  /*
  |--------------------------------------------------------------------------
  | Add Item
  |--------------------------------------------------------------------------
  */

  function addItem(
    product: CartProduct,
    quantity = 1
  ) {
    setItems((current) => {
      const existing =
        current.find(
          (item) =>
            item.id === product.id
        );

      if (existing) {
        return current.map(
          (item) => {
            if (
              item.id !==
              product.id
            ) {
              return item;
            }

            const nextQuantity =
              Math.min(
                item.quantity +
                  quantity,
                item.stock
              );

            return {
              ...item,
              quantity:
                nextQuantity,
            };
          }
        );
      }

      return [
        ...current,
        {
          ...product,

          quantity:
            Math.min(
              Math.max(
                quantity,
                1
              ),
              product.stock
            ),
        },
      ];
    });
  }


  /*
  |--------------------------------------------------------------------------
  | Remove Item
  |--------------------------------------------------------------------------
  */

  function removeItem(
    productId: number
  ) {
    setItems((current) =>
      current.filter(
        (item) =>
          item.id !==
          productId
      )
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Update Quantity
  |--------------------------------------------------------------------------
  */

  function updateQuantity(
    productId: number,
    quantity: number
  ) {
    if (quantity <= 0) {
      removeItem(productId);
      return;
    }

    setItems((current) =>
      current.map(
        (item) => {
          if (
            item.id !==
            productId
          ) {
            return item;
          }

          return {
            ...item,

            quantity:
              Math.min(
                quantity,
                item.stock
              ),
          };
        }
      )
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Clear
  |--------------------------------------------------------------------------
  */

  function clearCart() {
    setItems([]);
  }


  /*
  |--------------------------------------------------------------------------
  | Totals
  |--------------------------------------------------------------------------
  */

  const itemCount =
    items.reduce(
      (
        total,
        item
      ) =>
        total +
        item.quantity,
      0
    );


  const subtotal =
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


  const value =
    useMemo(
      () => ({
        items,

        addItem,
        removeItem,
        updateQuantity,
        clearCart,

        itemCount,
        subtotal,

        hydrated,
      }),
      [
        items,
        itemCount,
        subtotal,
        hydrated,
      ]
    );


  return (
    <CartContext.Provider
      value={value}
    >
      {children}
    </CartContext.Provider>
  );
}


export function useCart() {
  const context =
    useContext(CartContext);

  if (!context) {
    throw new Error(
      "useCart must be used inside CartProvider."
    );
  }

  return context;
}