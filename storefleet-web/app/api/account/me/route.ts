import { NextResponse } from "next/server";

import {
  clearCustomerSessionCookie,
  getCustomerSessionToken,
  storefleetCustomerRequest,
} from "@/lib/storefleet/customer-session";


/*
|--------------------------------------------------------------------------
| GET /api/account/me
|--------------------------------------------------------------------------
|
| Reads the StoreFleet customer session from the HttpOnly cookie.
|
| Browser
|   ↓
| Next.js HttpOnly cookie
|   ↓
| WordPress StoreFleet customer API
|
| The browser never receives the raw session token.
|
*/

export async function GET() {
  try {
    /*
    |--------------------------------------------------------------------------
    | Read HttpOnly Session
    |--------------------------------------------------------------------------
    */

    const sessionToken =
      await getCustomerSessionToken();

    if (!sessionToken) {
      return NextResponse.json(
        {
          success: false,
          authenticated: false,
          customer: null,
        },
        {
          status: 401,
        }
      );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Session Against WordPress
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

    const payload =
      await response.json();


    /*
    |--------------------------------------------------------------------------
    | Invalid / Expired Session
    |--------------------------------------------------------------------------
    */

    if (!response.ok) {
      if (
        response.status === 401 ||
        response.status === 403
      ) {
        await clearCustomerSessionCookie();
      }

      return NextResponse.json(
        {
          success: false,
          authenticated: false,
          customer: null,
          error: payload,
        },
        {
          status: response.status,
        }
      );
    }


    /*
    |--------------------------------------------------------------------------
    | Safe Browser Response
    |--------------------------------------------------------------------------
    */

    return NextResponse.json(
      {
        success: true,
        authenticated: true,
        customer:
          payload.customer ?? payload,
      },
      {
        status: 200,
      }
    );
  } catch (error) {
    console.error(
      "StoreFleet customer session validation error:",
      error
    );

    return NextResponse.json(
      {
        success: false,
        authenticated: false,
        customer: null,
        message:
          "Unable to validate customer session.",
      },
      {
        status: 500,
      }
    );
  }
}