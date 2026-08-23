import { NextResponse } from "next/server";

import {
  clearCustomerSessionCookie,
  getCustomerSessionToken,
  storefleetCustomerRequest,
} from "@/lib/storefleet/customer-session";


/*
|--------------------------------------------------------------------------
| POST /api/account/logout
|--------------------------------------------------------------------------
|
| Invalidates the StoreFleet customer session in WordPress and then clears
| the HttpOnly session cookie in Next.js.
|
*/

export async function POST() {
  try {
    const sessionToken =
      await getCustomerSessionToken();

    /*
    |--------------------------------------------------------------------------
    | No Local Session
    |--------------------------------------------------------------------------
    */

    if (!sessionToken) {
      await clearCustomerSessionCookie();

      return NextResponse.json(
        {
          success: true,
          logged_out: true,
        },
        {
          status: 200,
        }
      );
    }


    /*
    |--------------------------------------------------------------------------
    | Destroy WordPress Session
    |--------------------------------------------------------------------------
    */

    const response =
      await storefleetCustomerRequest(
        "/wp-json/storefleet/v1/customers/logout",
        {
          method: "POST",
          sessionToken,
        }
      );

    let payload: unknown = null;

    try {
      payload =
        await response.json();
    } catch {
      payload = null;
    }


    /*
    |--------------------------------------------------------------------------
    | Always Clear Browser Cookie
    |--------------------------------------------------------------------------
    |
    | Even if WordPress says the session is already invalid or expired,
    | the browser cookie should still be removed.
    |
    */

    await clearCustomerSessionCookie();


    /*
    |--------------------------------------------------------------------------
    | WordPress Logout Error
    |--------------------------------------------------------------------------
    */

    if (!response.ok) {
      return NextResponse.json(
        {
          success: false,
          logged_out: true,
          message:
            "Local customer session was cleared, but the server session could not be confirmed.",
          error: payload,
        },
        {
          status: response.status,
        }
      );
    }


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    return NextResponse.json(
      {
        success: true,
        logged_out: true,
      },
      {
        status: 200,
      }
    );
  } catch (error) {
    console.error(
      "StoreFleet customer logout proxy error:",
      error
    );

    /*
    |--------------------------------------------------------------------------
    | Fail Closed Locally
    |--------------------------------------------------------------------------
    |
    | Remove the local cookie even when the upstream request fails.
    |
    */

    await clearCustomerSessionCookie();

    return NextResponse.json(
      {
        success: false,
        logged_out: true,
        message:
          "Customer session was cleared locally, but logout could not be fully confirmed.",
      },
      {
        status: 502,
      }
    );
  }
}