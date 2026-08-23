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


    /*
    |--------------------------------------------------------------------------
    | Validate Request
    |--------------------------------------------------------------------------
    */

    const email =
      body.email?.trim() ?? "";

    const password =
      body.password ?? "";

    const remember =
      body.remember === true;


    if (!email || !password) {
      return NextResponse.json(
        {
          success: false,
          message:
            "Email and password are required.",
        },
        {
          status: 400,
        }
      );
    }


    /*
    |--------------------------------------------------------------------------
    | WordPress Login
    |--------------------------------------------------------------------------
    |
    | StoreFleet's WordPress endpoint accepts either an email address or a
    | username through the "login" field.
    |
    */

    const response =
      await storefleetCustomerRequest(
        "/wp-json/storefleet/v1/customers/login",
        {
          method: "POST",

          body: {
            login: email,
            password,
            remember,
          },
        }
      );


    const payload =
      await response.json();


    /*
    |--------------------------------------------------------------------------
    | Login Failed
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
    | Extract Session
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Store HttpOnly Session
    |--------------------------------------------------------------------------
    */

    await setCustomerSessionCookie(
      session
    );


    /*
    |--------------------------------------------------------------------------
    | Safe Browser Response
    |--------------------------------------------------------------------------
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