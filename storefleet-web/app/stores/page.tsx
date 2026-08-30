import type { Metadata } from "next";

import StoresBrowser from "@/components/stores-browser";
import { getStores } from "@/lib/storefleet-api";


export const metadata: Metadata = {
  title: "Stores",
  description:
    "Discover local merchants and StoreFleet stores.",
};


export default async function StoresPage() {
  const response =
    await getStores();

  return (
    <StoresBrowser
      stores={response.stores}
    />
  );
}
