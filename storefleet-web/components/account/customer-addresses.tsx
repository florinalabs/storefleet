"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";

import {
  useState,
  type FormEvent,
} from "react";


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


type AddressForm = {
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

  is_default: boolean;
};


type ApiResponse = {
  success?: boolean;
  message?: string;

  addresses?: Address[];

  address?: Address;
};


type CustomerAddressesProps = {
  initialAddresses: Address[];
};


const EMPTY_FORM: AddressForm = {
  label: "Home",

  first_name: "",
  last_name: "",

  phone: "",

  address_1: "",
  address_2: "",

  barangay: "",
  city: "",
  province: "",
  postcode: "",

  is_default: false,
};


export default function CustomerAddresses({
  initialAddresses,
}: CustomerAddressesProps) {
  const router = useRouter();


  const [addresses, setAddresses] =
    useState<Address[]>(
      initialAddresses
    );

  const [form, setForm] =
    useState<AddressForm>(
      EMPTY_FORM
    );

  const [editingId, setEditingId] =
    useState<string | null>(null);

  const [showForm, setShowForm] =
    useState(false);

  const [saving, setSaving] =
    useState(false);

  const [workingId, setWorkingId] =
    useState<string | null>(null);

  const [error, setError] =
    useState<string | null>(null);

  const [success, setSuccess] =
    useState<string | null>(null);


  /*
  |--------------------------------------------------------------------------
  | Reload Addresses
  |--------------------------------------------------------------------------
  |
  | Initial addresses are loaded server-side by the page component.
  |
  | This function is only called after Add, Edit, Delete,
  | or Set Default operations.
  |
  */

  async function loadAddresses() {
    try {
      const response =
        await fetch(
          "/api/account/addresses",
          {
            method: "GET",
            cache: "no-store",
          }
        );


      if (
        response.status === 401 ||
        response.status === 403
      ) {
        router.push(
          "/account/login?next=/account/addresses"
        );

        return;
      }


      const payload =
        (await response.json()) as ApiResponse;


      if (!response.ok) {
        setError(
          payload.message ||
            "Unable to load your delivery addresses."
        );

        return;
      }


      setAddresses(
        payload.addresses ?? []
      );
    } catch (error) {
      console.error(
        "Customer addresses load error:",
        error
      );


      setError(
        "Unable to load your delivery addresses."
      );
    }
  }


  /*
  |--------------------------------------------------------------------------
  | Form Helpers
  |--------------------------------------------------------------------------
  */

  function updateField(
    field: keyof AddressForm,
    value: string | boolean
  ) {
    setForm(
      (current) => ({
        ...current,
        [field]: value,
      })
    );
  }


  function startAddAddress() {
    setEditingId(null);

    setForm(
      EMPTY_FORM
    );

    setError(null);
    setSuccess(null);

    setShowForm(true);
  }


  function startEditAddress(
    address: Address
  ) {
    setEditingId(
      address.id
    );


    setForm({
      label:
        address.label || "Home",

      first_name:
        address.first_name,

      last_name:
        address.last_name,

      phone:
        address.phone,

      address_1:
        address.address_1,

      address_2:
        address.address_2,

      barangay:
        address.barangay,

      city:
        address.city,

      province:
        address.province,

      postcode:
        address.postcode,

      is_default:
        address.is_default,
    });


    setError(null);
    setSuccess(null);

    setShowForm(true);


    window.scrollTo({
      top: 0,
      behavior: "smooth",
    });
  }


  function cancelForm() {
    setShowForm(false);

    setEditingId(null);

    setForm(
      EMPTY_FORM
    );

    setError(null);
  }


  /*
  |--------------------------------------------------------------------------
  | Save Address
  |--------------------------------------------------------------------------
  */

  async function handleSubmit(
    event: FormEvent<HTMLFormElement>
  ) {
    event.preventDefault();


    setError(null);
    setSuccess(null);


    if (
      !form.first_name.trim() ||
      !form.last_name.trim() ||
      !form.phone.trim() ||
      !form.address_1.trim() ||
      !form.city.trim() ||
      !form.province.trim()
    ) {
      setError(
        "Please complete all required address fields."
      );

      return;
    }


    setSaving(true);


    try {
      const endpoint =
        editingId
          ? `/api/account/addresses/${encodeURIComponent(
              editingId
            )}`
          : "/api/account/addresses";


      const response =
        await fetch(
          endpoint,
          {
            method:
              editingId
                ? "PATCH"
                : "POST",

            headers: {
              "Content-Type":
                "application/json",
            },

            body: JSON.stringify({
              label:
                form.label.trim(),

              first_name:
                form.first_name.trim(),

              last_name:
                form.last_name.trim(),

              phone:
                form.phone.trim(),

              address_1:
                form.address_1.trim(),

              address_2:
                form.address_2.trim(),

              barangay:
                form.barangay.trim(),

              city:
                form.city.trim(),

              province:
                form.province.trim(),

              postcode:
                form.postcode.trim(),

              country:
                "PH",

              is_default:
                form.is_default,
            }),
          }
        );


      if (
        response.status === 401 ||
        response.status === 403
      ) {
        router.push(
          "/account/login?next=/account/addresses"
        );

        return;
      }


      const payload =
        (await response.json()) as ApiResponse;


      if (!response.ok) {
        setError(
          payload.message ||
            "Unable to save delivery address."
        );

        return;
      }


      setSuccess(
        editingId
          ? "Delivery address updated."
          : "Delivery address added."
      );


      setShowForm(false);

      setEditingId(null);

      setForm(
        EMPTY_FORM
      );


      await loadAddresses();
    } catch (error) {
      console.error(
        "Customer address save error:",
        error
      );


      setError(
        "Unable to save delivery address."
      );
    } finally {
      setSaving(false);
    }
  }


  /*
  |--------------------------------------------------------------------------
  | Delete Address
  |--------------------------------------------------------------------------
  */

  async function deleteAddress(
    address: Address
  ) {
    const confirmed =
      window.confirm(
        `Delete "${address.label}" address?`
      );


    if (!confirmed) {
      return;
    }


    setWorkingId(
      address.id
    );

    setError(null);
    setSuccess(null);


    try {
      const response =
        await fetch(
          `/api/account/addresses/${encodeURIComponent(
            address.id
          )}`,
          {
            method: "DELETE",
          }
        );


      if (
        response.status === 401 ||
        response.status === 403
      ) {
        router.push(
          "/account/login?next=/account/addresses"
        );

        return;
      }


      const payload =
        (await response.json()) as ApiResponse;


      if (!response.ok) {
        setError(
          payload.message ||
            "Unable to delete delivery address."
        );

        return;
      }


      setSuccess(
        "Delivery address deleted."
      );


      await loadAddresses();
    } catch (error) {
      console.error(
        "Customer address delete error:",
        error
      );


      setError(
        "Unable to delete delivery address."
      );
    } finally {
      setWorkingId(null);
    }
  }


  /*
  |--------------------------------------------------------------------------
  | Set Default
  |--------------------------------------------------------------------------
  */

  async function setDefaultAddress(
    address: Address
  ) {
    if (address.is_default) {
      return;
    }


    setWorkingId(
      address.id
    );

    setError(null);
    setSuccess(null);


    try {
      const response =
        await fetch(
          `/api/account/addresses/${encodeURIComponent(
            address.id
          )}/default`,
          {
            method: "POST",
          }
        );


      if (
        response.status === 401 ||
        response.status === 403
      ) {
        router.push(
          "/account/login?next=/account/addresses"
        );

        return;
      }


      const payload =
        (await response.json()) as ApiResponse;


      if (!response.ok) {
        setError(
          payload.message ||
            "Unable to update the default address."
        );

        return;
      }


      setSuccess(
        "Default delivery address updated."
      );


      await loadAddresses();
    } catch (error) {
      console.error(
        "Customer default address error:",
        error
      );


      setError(
        "Unable to update the default address."
      );
    } finally {
      setWorkingId(null);
    }
  }


  /*
  |--------------------------------------------------------------------------
  | Render
  |--------------------------------------------------------------------------
  */

  return (
    <section className="min-h-[70vh] bg-zinc-50">

      <div className="mx-auto w-full max-w-7xl px-5 py-12 sm:px-6 lg:px-8">

        {/* Header */}

        <div className="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">

          <div>

            <Link
              href="/account"
              className="text-sm font-semibold text-zinc-500 transition hover:text-violet-600"
            >
              ← My Account
            </Link>


            <p className="mt-6 text-sm font-semibold text-violet-600">
              Delivery Addresses
            </p>


            <h1 className="mt-2 text-3xl font-black tracking-tight text-zinc-950 sm:text-4xl">
              Your addresses
            </h1>


            <p className="mt-3 max-w-2xl text-sm leading-6 text-zinc-500 sm:text-base">
              Save delivery locations for
              faster StoreFleet checkout.
            </p>

          </div>


          {!showForm && (
            <button
              type="button"
              onClick={
                startAddAddress
              }
              className="inline-flex min-h-12 items-center justify-center rounded-full bg-violet-600 px-6 text-sm font-bold text-white transition hover:bg-violet-700"
            >
              Add Address
            </button>
          )}

        </div>


        {/* Messages */}

        {error && (
          <div
            role="alert"
            className="mt-8 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700"
          >
            {error}
          </div>
        )}


        {success && (
          <div className="mt-8 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-700">
            {success}
          </div>
        )}


        {/* Address Form */}

        {showForm && (
          <div className="mt-8 rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">

            <div className="mb-8">

              <h2 className="text-xl font-black">
                {editingId
                  ? "Edit address"
                  : "Add delivery address"}
              </h2>


              <p className="mt-2 text-sm text-zinc-500">
                Enter the address information
                StoreFleet should use for
                delivery.
              </p>

            </div>


            <form
              onSubmit={
                handleSubmit
              }
              className="space-y-6"
            >

              {/* Label */}

              <AddressInput
                label="Address label"
                value={
                  form.label
                }
                onChange={(value) =>
                  updateField(
                    "label",
                    value
                  )
                }
                placeholder="Home"
              />


              {/* Name */}

              <div className="grid gap-6 sm:grid-cols-2">

                <AddressInput
                  label="First name"
                  value={
                    form.first_name
                  }
                  onChange={(value) =>
                    updateField(
                      "first_name",
                      value
                    )
                  }
                  required
                />


                <AddressInput
                  label="Last name"
                  value={
                    form.last_name
                  }
                  onChange={(value) =>
                    updateField(
                      "last_name",
                      value
                    )
                  }
                  required
                />

              </div>


              {/* Phone */}

              <AddressInput
                label="Mobile number"
                type="tel"
                value={
                  form.phone
                }
                onChange={(value) =>
                  updateField(
                    "phone",
                    value
                  )
                }
                placeholder="+63 917 123 4567"
                required
              />


              {/* Street */}

              <AddressInput
                label="Street address"
                value={
                  form.address_1
                }
                onChange={(value) =>
                  updateField(
                    "address_1",
                    value
                  )
                }
                placeholder="House number, street, subdivision"
                required
              />


              <AddressInput
                label="Apartment, unit, building"
                value={
                  form.address_2
                }
                onChange={(value) =>
                  updateField(
                    "address_2",
                    value
                  )
                }
                placeholder="Optional"
              />


              {/* Location */}

              <div className="grid gap-6 sm:grid-cols-2">

                <AddressInput
                  label="Barangay"
                  value={
                    form.barangay
                  }
                  onChange={(value) =>
                    updateField(
                      "barangay",
                      value
                    )
                  }
                />


                <AddressInput
                  label="City / Municipality"
                  value={
                    form.city
                  }
                  onChange={(value) =>
                    updateField(
                      "city",
                      value
                    )
                  }
                  required
                />


                <AddressInput
                  label="Province"
                  value={
                    form.province
                  }
                  onChange={(value) =>
                    updateField(
                      "province",
                      value
                    )
                  }
                  required
                />


                <AddressInput
                  label="Postal code"
                  value={
                    form.postcode
                  }
                  onChange={(value) =>
                    updateField(
                      "postcode",
                      value
                    )
                  }
                />

              </div>


              {/* Default */}

              <label className="flex items-start gap-3 rounded-2xl border border-zinc-200 p-4">

                <input
                  type="checkbox"
                  checked={
                    form.is_default
                  }
                  onChange={(event) =>
                    updateField(
                      "is_default",
                      event.target.checked
                    )
                  }
                  className="mt-1 h-4 w-4"
                />


                <span>

                  <span className="block text-sm font-semibold">
                    Make this my default address
                  </span>


                  <span className="mt-1 block text-xs leading-5 text-zinc-500">
                    StoreFleet will use this
                    address first during
                    checkout.
                  </span>

                </span>

              </label>


              {/* Actions */}

              <div className="flex flex-wrap gap-3">

                <button
                  type="submit"
                  disabled={
                    saving
                  }
                  className="inline-flex min-h-12 items-center justify-center rounded-full bg-violet-600 px-6 text-sm font-bold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {saving
                    ? "Saving..."
                    : editingId
                      ? "Save Changes"
                      : "Save Address"}
                </button>


                <button
                  type="button"
                  onClick={
                    cancelForm
                  }
                  disabled={
                    saving
                  }
                  className="inline-flex min-h-12 items-center justify-center rounded-full border border-zinc-300 px-6 text-sm font-bold text-zinc-700 transition hover:bg-zinc-50"
                >
                  Cancel
                </button>

              </div>

            </form>

          </div>
        )}


        {/* Address List */}

        <div className="mt-8">

          {addresses.length === 0 ? (

            <div className="rounded-3xl border border-zinc-200 bg-white px-6 py-16 text-center shadow-sm">

              <h2 className="text-xl font-black">
                No saved addresses yet
              </h2>


              <p className="mx-auto mt-3 max-w-md text-sm leading-6 text-zinc-500">
                Add a delivery address so
                StoreFleet can use it during
                checkout.
              </p>


              {!showForm && (
                <button
                  type="button"
                  onClick={
                    startAddAddress
                  }
                  className="mt-6 inline-flex min-h-12 items-center justify-center rounded-full bg-violet-600 px-6 text-sm font-bold text-white transition hover:bg-violet-700"
                >
                  Add Your First Address
                </button>
              )}

            </div>

          ) : (

            <div className="grid gap-6 md:grid-cols-2">

              {addresses.map(
                (address) => (

                  <AddressCard
                    key={
                      address.id
                    }
                    address={
                      address
                    }
                    working={
                      workingId ===
                      address.id
                    }
                    onEdit={() =>
                      startEditAddress(
                        address
                      )
                    }
                    onDelete={() =>
                      void deleteAddress(
                        address
                      )
                    }
                    onSetDefault={() =>
                      void setDefaultAddress(
                        address
                      )
                    }
                  />

                )
              )}

            </div>

          )}

        </div>

      </div>

    </section>
  );
}


/*
|--------------------------------------------------------------------------
| Address Input
|--------------------------------------------------------------------------
*/

function AddressInput({
  label,
  value,
  onChange,
  placeholder,
  required = false,
  type = "text",
}: {
  label: string;
  value: string;
  onChange: (
    value: string
  ) => void;
  placeholder?: string;
  required?: boolean;
  type?: string;
}) {
  return (
    <div>

      <label className="mb-2 block text-sm font-semibold text-zinc-800">
        {label}

        {required && (
          <span className="text-red-500">
            {" "}*
          </span>
        )}
      </label>


      <input
        type={type}
        value={value}
        onChange={(event) =>
          onChange(
            event.target.value
          )
        }
        placeholder={
          placeholder
        }
        required={
          required
        }
        className="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm outline-none transition placeholder:text-zinc-400 focus:border-violet-600 focus:ring-2 focus:ring-violet-600/10"
      />

    </div>
  );
}


/*
|--------------------------------------------------------------------------
| Address Card
|--------------------------------------------------------------------------
*/

function AddressCard({
  address,
  working,
  onEdit,
  onDelete,
  onSetDefault,
}: {
  address: Address;
  working: boolean;
  onEdit: () => void;
  onDelete: () => void;
  onSetDefault: () => void;
}) {
  return (
    <article className="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">

      <div className="flex items-start justify-between gap-4">

        <div>

          <div className="flex flex-wrap items-center gap-2">

            <h2 className="text-lg font-black">
              {address.label}
            </h2>


            {address.is_default && (
              <span className="rounded-full bg-violet-100 px-3 py-1 text-xs font-bold text-violet-700">
                Default
              </span>
            )}

          </div>


          <p className="mt-4 text-sm font-semibold text-zinc-900">
            {address.first_name}{" "}
            {address.last_name}
          </p>


          <div className="mt-2 space-y-1 text-sm leading-6 text-zinc-500">

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

        </div>

      </div>


      <div className="mt-6 flex flex-wrap gap-4 border-t border-zinc-100 pt-5">

        <button
          type="button"
          onClick={
            onEdit
          }
          disabled={
            working
          }
          className="text-sm font-bold text-violet-600 transition hover:text-violet-700 disabled:opacity-50"
        >
          Edit
        </button>


        {!address.is_default && (
          <button
            type="button"
            onClick={
              onSetDefault
            }
            disabled={
              working
            }
            className="text-sm font-bold text-zinc-600 transition hover:text-violet-600 disabled:opacity-50"
          >
            Set Default
          </button>
        )}


        <button
          type="button"
          onClick={
            onDelete
          }
          disabled={
            working
          }
          className="text-sm font-bold text-red-600 transition hover:text-red-700 disabled:opacity-50"
        >
          {working
            ? "Working..."
            : "Delete"}
        </button>

      </div>

    </article>
  );
}