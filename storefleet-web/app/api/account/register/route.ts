import { NextResponse } from "next/server";

import {
  extractSession,
  setCustomerSessionCookie,
  storefleetCustomerRequest,
} from "@/lib/storefleet/customer-session";


type RegistrationPayload = {
  first_name?: string;
  last_name?: string;
  email?: string;
  phone?: string;
  password?: string;
};


/*
|--------------------------------------------------------------------------
| POST /api/account/register
|--------------------------------------------------------------------------
|
| Browser
|   ↓
| Next.js
|   ↓
| StoreFleet WordPress API
|
| The internal StoreFleet API key and raw customer session token remain
| server-side.
|
*/

export async function POST(
  request: Request
) {
  try {
    const body =
      (await request.json()) as RegistrationPayload;

    const response =
      await storefleetCustomerRequest(
        "/wp-json/storefleet/v1/customers/register",
        {
          method: "POST",
          body,
        }
      );

    const payload =
      await response.json();

    /*
    |--------------------------------------------------------------------------
    | WordPress Registration Error
    |--------------------------------------------------------------------------
    */

    if (!response.ok) {
      return NextResponse.json(
        payload,
        {
          status: response.status,
        }
      );
    }

    /*
    |--------------------------------------------------------------------------
    | Extract StoreFleet Session
    |--------------------------------------------------------------------------
    */

    const session =
      extractSession(payload);

    if (!session) {
      console.error(
        "StoreFleet registration succeeded but no customer session was returned."
      );

      return NextResponse.json(
        {
          success: false,
          message:
            "Registration succeeded but the customer session could not be created.",
        },
        {
          status: 502,
        }
      );
    }

    /*
    |--------------------------------------------------------------------------
    | Store Raw Token In HttpOnly Cookie
    |--------------------------------------------------------------------------
    */

    await setCustomerSessionCookie(
      session
    );

    /*
    |--------------------------------------------------------------------------
    | Safe Browser Response
    |--------------------------------------------------------------------------
    |
    | Never return the raw session token to browser JavaScript.
    |
    */

    return NextResponse.json(
      {
        success: true,
        customer:
          payload.customer ?? null,
      },
      {
        status: response.status,
      }
    );
  } catch (error) {
    console.error(
      "StoreFleet customer registration proxy error:",
      error
    );

    return NextResponse.json(
      {
        success: false,
        message:
          "Unable to register customer at this time.",
      },
      {
        status: 500,
      }
    );
  }
}