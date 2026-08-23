import { NextResponse } from "next/server";

import {
  extractSession,
  setCustomerSessionCookie,
  storefleetCustomerRequest,
} from "@/lib/storefleet/customer-session";


type LoginPayload = {
  email?: string;
  password?: string;
  remember?: boolean;
};


/*
|--------------------------------------------------------------------------
| POST /api/account/login
|--------------------------------------------------------------------------
*/

export async function POST(
  request: Request
) {
  try {
    const body =
      (await request.json()) as LoginPayload;

    const response =
      await storefleetCustomerRequest(
        "/wp-json/storefleet/v1/customers/login",
        {
          method: "POST",
          body,
        }
      );

    const payload =
      await response.json();

    if (!response.ok) {
      return NextResponse.json(
        payload,
        {
          status: response.status,
        }
      );
    }

    const session =
      extractSession(payload);

    if (!session) {
      console.error(
        "StoreFleet login succeeded but no customer session was returned."
      );

      return NextResponse.json(
        {
          success: false,
          message:
            "Login succeeded but the customer session could not be created.",
        },
        {
          status: 502,
        }
      );
    }

    await setCustomerSessionCookie(
      session
    );

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
      "StoreFleet customer login proxy error:",
      error
    );

    return NextResponse.json(
      {
        success: false,
        message:
          "Unable to log in at this time.",
      },
      {
        status: 500,
      }
    );
  }
}