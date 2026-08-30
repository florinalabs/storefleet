import Link from "next/link";
import { redirect } from "next/navigation";

import CheckoutAddressSelector, {
  type CheckoutAddress,
} from "@/components/checkout/checkout-address-selector";

import CheckoutOrderSummary from "@/components/checkout/checkout-order-summary";

import {
  getCustomerSessionToken,
  storefleetCustomerRequest,
} from "@/lib/storefleet/customer-session";


type Customer = {
  id?: number;
  first_name?: string;
  last_name?: string;
  display_name?: string;
  email?: string;
  phone?: string;
};


type MeResponse = {
  success?: boolean;
  customer?: Customer | null;
};


type AddressesResponse = {
  success?: boolean;
  addresses?: CheckoutAddress[];
};


/*
|--------------------------------------------------------------------------
| Checkout
|--------------------------------------------------------------------------
|
| Checkout is customer-only.
|
| Visitors may browse and use the cart without signing in, but they must
| have a valid StoreFleet customer session before entering checkout.
|
*/

export default async function CheckoutPage() {
  /*
  |--------------------------------------------------------------------------
  | Require Customer Session
  |--------------------------------------------------------------------------
  */

  const sessionToken =
    await getCustomerSessionToken();


  if (!sessionToken) {
    redirect(
      "/account/login?next=/checkout"
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Load Customer + Delivery Addresses
  |--------------------------------------------------------------------------
  */

  const [
    customerResponse,
    addressesResponse,
  ] =
    await Promise.all([
      storefleetCustomerRequest(
        "/wp-json/storefleet/v1/customers/me",
        {
          method: "GET",
          sessionToken,
        }
      ),

      storefleetCustomerRequest(
        "/wp-json/storefleet/v1/customers/addresses",
        {
          method: "GET",
          sessionToken,
        }
      ),
    ]);


  /*
  |--------------------------------------------------------------------------
  | Validate Customer Session
  |--------------------------------------------------------------------------
  */

  if (
    customerResponse.status === 401 ||
    customerResponse.status === 403 ||
    addressesResponse.status === 401 ||
    addressesResponse.status === 403
  ) {
    redirect(
      "/account/login?next=/checkout"
    );
  }


  if (!customerResponse.ok) {
    redirect(
      "/account/login?next=/checkout"
    );
  }


  const customerPayload =
    (await customerResponse.json()) as MeResponse;


  const customer =
    customerPayload.customer;


  if (!customer) {
    redirect(
      "/account/login?next=/checkout"
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Delivery Addresses
  |--------------------------------------------------------------------------
  */

  let addresses:
    CheckoutAddress[] =
      [];


  let addressLoadError =
    false;


  if (addressesResponse.ok) {
    try {
      const addressPayload =
        (await addressesResponse.json()) as AddressesResponse;


      addresses =
        addressPayload.addresses ??
        [];
    } catch (error) {
      console.error(
        "StoreFleet checkout address parse error:",
        error
      );


      addressLoadError =
        true;
    }
  } else {
    addressLoadError =
      true;
  }


  /*
  |--------------------------------------------------------------------------
  | Customer Display Name
  |--------------------------------------------------------------------------
  */

  const customerName =
    customer.first_name?.trim() ||
    customer.display_name?.trim() ||
    "Customer";


  return (
    <section className="min-h-[70vh] bg-zinc-50">

      <div className="mx-auto w-full max-w-7xl px-5 py-12 sm:px-6 lg:px-8">

        {/* Header */}

        <div className="mb-10">

          <p className="text-sm font-semibold text-violet-600">
            Checkout
          </p>


          <h1 className="mt-2 text-3xl font-black tracking-tight text-zinc-950 sm:text-4xl">
            Complete your order
          </h1>


          <p className="mt-3 max-w-2xl text-sm leading-6 text-zinc-500 sm:text-base">
            Hi, {customerName}. Review your
            delivery and payment information
            before placing your order.
          </p>

        </div>


        {/* Checkout Layout */}

        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_400px]">

          {/* Checkout Details */}

          <div className="space-y-6">

            {/* Customer */}

            <CheckoutSection
              number="1"
              title="Customer"
            >

              <div className="grid gap-6 sm:grid-cols-2">

                <CheckoutField
                  label="Name"
                  value={
                    customer.display_name ||
                    [
                      customer.first_name,
                      customer.last_name,
                    ]
                      .filter(Boolean)
                      .join(" ") ||
                    "Customer"
                  }
                />


                <CheckoutField
                  label="Email"
                  value={
                    customer.email ||
                    "Not available"
                  }
                />


                <CheckoutField
                  label="Mobile number"
                  value={
                    customer.phone ||
                    "Not available"
                  }
                />

              </div>

            </CheckoutSection>


            {/* Delivery Address */}

            <CheckoutSection
              number="2"
              title="Delivery Address"
            >

              <CheckoutAddressSelector
                addresses={
                  addresses
                }
                loadError={
                  addressLoadError
                }
              />

            </CheckoutSection>


            {/* Delivery */}

            <CheckoutSection
              number="3"
              title="Delivery"
            >

              <div className="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 px-5 py-8">

                <p className="text-sm font-semibold text-zinc-900">
                  Delivery quotation
                </p>


                <p className="mt-2 text-sm leading-6 text-zinc-500">
                  Lalamove delivery options and
                  quotation will appear here
                  after the delivery address is
                  confirmed.
                </p>


                <span className="mt-4 inline-flex rounded-full bg-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-600">
                  Coming soon
                </span>

              </div>

            </CheckoutSection>


            {/* Payment */}

            <CheckoutSection
              number="4"
              title="Payment"
            >

              <div className="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 px-5 py-8">

                <p className="text-sm font-semibold text-zinc-900">
                  Online payment
                </p>


                <p className="mt-2 text-sm leading-6 text-zinc-500">
                  Secure online payment through
                  StoreFleet checkout will appear
                  here.
                </p>


                <span className="mt-4 inline-flex rounded-full bg-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-600">
                  Coming soon
                </span>

              </div>

            </CheckoutSection>

          </div>


          {/* Live Cart Order Summary */}

          <CheckoutOrderSummary />

        </div>

      </div>

    </section>
  );
}


/*
|--------------------------------------------------------------------------
| Checkout Section
|--------------------------------------------------------------------------
*/

function CheckoutSection({
  number,
  title,
  children,
}: {
  number: string;
  title: string;
  children: React.ReactNode;
}) {
  return (
    <div className="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">

      <div className="mb-6 flex items-center gap-3">

        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-950 text-xs font-bold text-white">
          {number}
        </span>


        <h2 className="text-lg font-bold text-zinc-950">
          {title}
        </h2>

      </div>


      {children}

    </div>
  );
}


/*
|--------------------------------------------------------------------------
| Checkout Field
|--------------------------------------------------------------------------
*/

function CheckoutField({
  label,
  value,
}: {
  label: string;
  value: string;
}) {
  return (
    <div>

      <p className="text-xs font-semibold uppercase tracking-wide text-zinc-400">
        {label}
      </p>


      <p className="mt-2 break-words text-sm font-semibold text-zinc-900">
        {value}
      </p>

    </div>
  );
}
