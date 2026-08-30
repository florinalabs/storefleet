import type { Metadata } from "next";

import ShopBrowser from "@/components/shop-browser";
import { getProducts } from "@/lib/storefleet-api";


export const metadata: Metadata = {
  title: "Shop",
  description:
    "Discover products from local StoreFleet merchants.",
};


export default async function ShopPage() {
  const response =
    await getProducts(
      1,
      100
    );

  return (
    <ShopBrowser
      products={response.products}
    />
  );
}
