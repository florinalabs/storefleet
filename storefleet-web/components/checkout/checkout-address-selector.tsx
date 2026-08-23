"use client";

import Link from "next/link";

import {
  useState,
} from "react";


export type CheckoutAddress = {
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


type CheckoutAddressSelectorProps = {
  addresses: CheckoutAddress[];
  loadError?: boolean;
};


/*
|--------------------------------------------------------------------------
| Checkout Address Selector
|--------------------------------------------------------------------------
*/

export default function CheckoutAddressSelector({
  addresses,
  loadError = false,
}: CheckoutAddressSelectorProps) {
  /*
  |--------------------------------------------------------------------------
  | Initial Selected Address
  |--------------------------------------------------------------------------
  */

  const defaultAddress =
    addresses.find(
      (address) =>
        address.is_default
    ) ??
    addresses[0] ??
    null;


  const [selectedAddressId, setSelectedAddressId] =
    useState<string>(
      defaultAddress?.id ?? ""
    );


  const [choosingAddress, setChoosingAddress] =
    useState(false);


  const selectedAddress =
    addresses.find(
      (address) =>
        address.id ===
        selectedAddressId
    ) ??
    defaultAddress;


  /*
  |--------------------------------------------------------------------------
  | Address API Error
  |--------------------------------------------------------------------------
  */

  if (loadError) {
    return (
      <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-6">

        <p className="text-sm font-bold text-red-800">
          Delivery addresses could not be loaded.
        </p>


        <p className="mt-2 text-sm leading-6 text-red-700">
          Please try refreshing checkout or
          manage your saved addresses from
          your account.
        </p>


        <Link
          href="/account/addresses"
          className="mt-5 inline-flex min-h-11 items-center justify-center rounded-full border border-red-300 bg-white px-5 text-sm font-bold text-red-700 transition hover:bg-red-100"
        >
          Manage Addresses
        </Link>

      </div>
    );
  }


  /*
  |--------------------------------------------------------------------------
  | No Addresses
  |--------------------------------------------------------------------------
  */

  if (
    addresses.length === 0 ||
    !selectedAddress
  ) {
    return (
      <div className="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-6">

        <p className="text-sm font-bold text-amber-900">
          Add a delivery address
        </p>


        <p className="mt-2 text-sm leading-6 text-amber-800">
          You need a saved delivery address
          before StoreFleet can calculate
          delivery and complete checkout.
        </p>


        <Link
          href="/account/addresses"
          className="mt-5 inline-flex min-h-11 items-center justify-center rounded-full bg-violet-600 px-5 text-sm font-bold text-white transition hover:bg-violet-700"
        >
          Add Delivery Address
        </Link>

      </div>
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Address Selection
  |--------------------------------------------------------------------------
  */

  if (choosingAddress) {
    return (
      <div>

        <div className="mb-5 flex flex-wrap items-center justify-between gap-3">

          <div>

            <p className="text-sm font-bold text-zinc-950">
              Choose a delivery address
            </p>


            <p className="mt-1 text-sm text-zinc-500">
              Select where this order should
              be delivered.
            </p>

          </div>


          <button
            type="button"
            onClick={() =>
              setChoosingAddress(false)
            }
            className="text-sm font-bold text-zinc-600 transition hover:text-violet-600"
          >
            Cancel
          </button>

        </div>


        <div className="space-y-4">

          {addresses.map(
            (address) => {

              const selected =
                address.id ===
                selectedAddress.id;


              return (
                <button
                  key={address.id}
                  type="button"
                  onClick={() => {
                    setSelectedAddressId(
                      address.id
                    );

                    setChoosingAddress(
                      false
                    );
                  }}
                  className={`w-full rounded-2xl border p-5 text-left transition ${
                    selected
                      ? "border-violet-500 bg-violet-50 ring-2 ring-violet-500/10"
                      : "border-zinc-200 bg-white hover:border-violet-300"
                  }`}
                >

                  <div className="flex items-start gap-4">

                    <span
                      className={`mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border ${
                        selected
                          ? "border-violet-600 bg-violet-600"
                          : "border-zinc-300 bg-white"
                      }`}
                    >
                      {selected && (
                        <span className="h-2 w-2 rounded-full bg-white" />
                      )}
                    </span>


                    <div className="min-w-0 flex-1">

                      <div className="flex flex-wrap items-center gap-2">

                        <p className="font-bold text-zinc-950">
                          {address.label}
                        </p>


                        {address.is_default && (
                          <span className="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-bold text-violet-700">
                            Default
                          </span>
                        )}

                      </div>


                      <AddressDetails
                        address={
                          address
                        }
                      />

                    </div>

                  </div>

                </button>
              );
            }
          )}

        </div>


        <Link
          href="/account/addresses"
          className="mt-5 inline-flex text-sm font-bold text-violet-600 transition hover:text-violet-700"
        >
          Manage saved addresses →
        </Link>

      </div>
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Selected Address
  |--------------------------------------------------------------------------
  */

  return (
    <div className="rounded-2xl border border-violet-200 bg-violet-50/40 p-5 sm:p-6">

      <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">

        <div className="min-w-0">

          <div className="flex flex-wrap items-center gap-2">

            <p className="text-base font-black text-zinc-950">
              {selectedAddress.label}
            </p>


            {selectedAddress.is_default && (
              <span className="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-bold text-violet-700">
                Default
              </span>
            )}


            <span className="rounded-full bg-green-100 px-2.5 py-1 text-xs font-bold text-green-700">
              Selected
            </span>

          </div>


          <AddressDetails
            address={
              selectedAddress
            }
          />

        </div>


        <div className="flex shrink-0 flex-wrap gap-3">

          {addresses.length > 1 && (
            <button
              type="button"
              onClick={() =>
                setChoosingAddress(
                  true
                )
              }
              className="text-sm font-bold text-violet-600 transition hover:text-violet-700"
            >
              Change
            </button>
          )}


          <Link
            href="/account/addresses"
            className="text-sm font-bold text-zinc-600 transition hover:text-violet-600"
          >
            Manage
          </Link>

        </div>

      </div>

    </div>
  );
}


/*
|--------------------------------------------------------------------------
| Address Details
|--------------------------------------------------------------------------
*/

function AddressDetails({
  address,
}: {
  address: CheckoutAddress;
}) {
  return (
    <div className="mt-3 space-y-1 text-sm leading-6 text-zinc-600">

      <p className="font-semibold text-zinc-900">
        {address.first_name}{" "}
        {address.last_name}
      </p>


      <p>
        {address.address_1}
      </p>


      {address.address_2 && (
        <p>
          {address.address_2}
        </p>
      )}


      <p>
        {[
          address.barangay,
          address.city,
          address.province,
          address.postcode,
        ]
          .filter(Boolean)
          .join(", ")}
      </p>


      <p>
        {address.phone}
      </p>

    </div>
  );
}