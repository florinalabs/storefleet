import { NextResponse } from "next/server";

import {
  clearCustomerSessionCookie,
  getCustomerSessionToken,
  storefleetCustomerRequest,
} from "@/lib/storefleet/customer-session";


type RouteContext = {
  params: Promise<{
    addressId: string;
  }>;
};


/*
|--------------------------------------------------------------------------
| POST /api/account/addresses/[addressId]/default
|--------------------------------------------------------------------------
*/

export async function POST(
  _request: Request,
  context: RouteContext
) {
  try {
    const sessionToken =
      await getCustomerSessionToken();


    if (!sessionToken) {
      return NextResponse.json(
        {
          success: false,
          message:
            "Customer authentication is required.",
        },
        {
          status: 401,
        }
      );
    }


    const { addressId } =
      await context.params;


    if (!addressId) {
      return NextResponse.json(
        {
          success: false,
          message:
            "Address ID is required.",
        },
        {
          status: 400,
        }
      );
    }


    const response =
      await storefleetCustomerRequest(
        `/wp-json/storefleet/v1/customers/addresses/${encodeURIComponent(
          addressId
        )}/default`,
        {
          method: "POST",
          sessionToken,
        }
      );


    const payload =
      await parseResponse(
        response
      );


    if (!response.ok) {
      if (
        response.status === 401 ||
        response.status === 403
      ) {
        await clearCustomerSessionCookie();
      }


      return NextResponse.json(
        payload,
        {
          status: response.status,
        }
      );
    }


    return NextResponse.json(
      payload,
      {
        status: response.status,
      }
    );
  } catch (error) {
    console.error(
      "StoreFleet default address update error:",
      error
    );


    return NextResponse.json(
      {
        success: false,
        message:
          "Unable to set the default delivery address.",
      },
      {
        status: 500,
      }
    );
  }
}


/*
|--------------------------------------------------------------------------
| Safe Response Parser
|--------------------------------------------------------------------------
*/

async function parseResponse(
  response: Response
): Promise<Record<string, unknown>> {
  try {
    return (
      await response.json()
    ) as Record<string, unknown>;
  } catch {
    return {
      success: false,
      message:
        "StoreFleet returned an invalid response.",
    };
  }
}