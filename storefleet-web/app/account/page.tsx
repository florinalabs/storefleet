import { redirect } from "next/navigation";

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


/*
|--------------------------------------------------------------------------
| Customer Account
|--------------------------------------------------------------------------
*/

export default async function CustomerAccountPage() {
  /*
  |--------------------------------------------------------------------------
  | Require Customer Session
  |--------------------------------------------------------------------------
  */

  const sessionToken =
    await getCustomerSessionToken();


  if (!sessionToken) {
    redirect("/account/login");
  }


  /*
  |--------------------------------------------------------------------------
  | Load Customer
  |--------------------------------------------------------------------------
  */

  const response =
    await storefleetCustomerRequest(
      "/wp-json/storefleet/v1/customers/me",
      {
        method: "GET",
        sessionToken,
      }
    );


  if (!response.ok) {
    redirect("/account/login");
  }


  const payload =
    (await response.json()) as MeResponse;


  const customer =
    payload.customer;


  if (!customer) {
    redirect("/account/login");
  }


  /*
  |--------------------------------------------------------------------------
  | Display Values
  |--------------------------------------------------------------------------
  */

  const fullName =
    customer.display_name?.trim() ||
    [
      customer.first_name,
      customer.last_name,
    ]
      .filter(Boolean)
      .join(" ") ||
    "Customer";


  const firstName =
    customer.first_name?.trim() ||
    fullName;


  return (
    <section className="min-h-[70vh] bg-zinc-50">

      <div className="mx-auto w-full max-w-7xl px-5 py-12 sm:px-6 lg:px-8">

        {/* Header */}

        <div className="mb-10">

          <p className="text-sm font-semibold text-violet-600">
            My Account
          </p>


          <h1 className="mt-2 text-3xl font-black tracking-tight text-zinc-950 sm:text-4xl">
            Welcome, {firstName}
          </h1>


          <p className="mt-3 max-w-2xl text-sm leading-6 text-zinc-500 sm:text-base">
            Manage your StoreFleet customer
            account, orders, delivery information,
            and payment methods.
          </p>

        </div>


        {/* Profile */}

        <div className="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">

          <div className="flex items-start justify-between gap-4">

            <div>

              <h2 className="text-lg font-bold text-zinc-950">
                Profile
              </h2>


              <p className="mt-1 text-sm text-zinc-500">
                Your StoreFleet customer
                information.
              </p>

            </div>


            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-zinc-950 text-lg font-bold text-white">
              {(
                customer.first_name?.[0] ||
                fullName[0] ||
                "C"
              ).toUpperCase()}
            </div>

          </div>


          <div className="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">

            <AccountField
              label="Name"
              value={fullName}
            />


            <AccountField
              label="Email"
              value={
                customer.email ||
                "Not available"
              }
            />


            <AccountField
              label="Mobile number"
              value={
                customer.phone ||
                "Not available"
              }
            />


            <AccountField
              label="Customer ID"
              value={
                customer.id
                  ? `#${customer.id}`
                  : "Not available"
              }
            />

          </div>

        </div>


        {/* Account Sections */}

        <div className="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">

          {/* Orders */}

          <AccountSection
            title="Orders"
            description="View your marketplace order history and order details."
          />


          {/* Addresses */}

          <AccountSection
            title="Addresses"
            description="Manage your saved delivery addresses for faster checkout."
          />


          {/* Payment Methods */}

          <AccountSection
            title="Payment Methods"
            description="Manage saved payment options for faster and secure checkout."
          />

        </div>

      </div>

    </section>
  );
}


/*
|--------------------------------------------------------------------------
| Account Field
|--------------------------------------------------------------------------
*/

function AccountField({
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


/*
|--------------------------------------------------------------------------
| Account Section
|--------------------------------------------------------------------------
*/

function AccountSection({
  title,
  description,
}: {
  title: string;
  description: string;
}) {
  return (
    <div className="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">

      <div className="flex items-start justify-between gap-4">

        <div>

          <h2 className="text-lg font-bold text-zinc-950">
            {title}
          </h2>


          <p className="mt-2 text-sm leading-6 text-zinc-500">
            {description}
          </p>

        </div>


        <span className="shrink-0 rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold text-zinc-500">
          Coming soon
        </span>

      </div>

    </div>
  );
}