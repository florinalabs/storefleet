import { redirect } from "next/navigation";

import CustomerAddresses from "@/components/account/customer-addresses";

import {
  getCustomerSessionToken,
  storefleetCustomerRequest,
} from "@/lib/storefleet/customer-session";


type Address = {
  id: string;
  label: string;

  first_name: string;
  last_name: string;

  phone: string;

  address_1: string;
  address_2: string;

  barangay: string;
  city: string;
  province: string;
  postcode: string;
  country: string;

  latitude: number | null;
  longitude: number | null;

  is_default: boolean;

  created_at?: string;
  updated_at?: string;
};


type AddressesResponse = {
  success?: boolean;
  addresses?: Address[];
};


export default async function CustomerAddressesPage() {
  /*
  |--------------------------------------------------------------------------
  | Require Customer Session
  |--------------------------------------------------------------------------
  */

  const sessionToken =
    await getCustomerSessionToken();


  if (!sessionToken) {
    redirect(
      "/account/login?next=/account/addresses"
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Load Initial Addresses
  |--------------------------------------------------------------------------
  */

  const response =
    await storefleetCustomerRequest(
      "/wp-json/storefleet/v1/customers/addresses",
      {
        method: "GET",
        sessionToken,
      }
    );


  if (
    response.status === 401 ||
    response.status === 403
  ) {
    redirect(
      "/account/login?next=/account/addresses"
    );
  }


  if (!response.ok) {
    return (
      <section className="min-h-[70vh] bg-zinc-50">

        <div className="mx-auto w-full max-w-7xl px-5 py-12 sm:px-6 lg:px-8">

          <div className="rounded-3xl border border-red-200 bg-red-50 px-6 py-8 text-sm text-red-700">
            Unable to load your delivery
            addresses.
          </div>

        </div>

      </section>
    );
  }


  const payload =
    (await response.json()) as AddressesResponse;


  return (
    <CustomerAddresses
      initialAddresses={
        payload.addresses ?? []
      }
    />
  );
}