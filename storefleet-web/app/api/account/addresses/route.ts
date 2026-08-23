import { NextResponse } from "next/server";

import {
  clearCustomerSessionCookie,
  getCustomerSessionToken,
  storefleetCustomerRequest,
} from "@/lib/storefleet/customer-session";


type AddressPayload = {
  label?: string;
  first_name?: string;
  last_name?: string;
  phone?: string;
  address_1?: string;
  address_2?: string;
  barangay?: string;
  city?: string;
  province?: string;
  postcode?: string;
  country?: string;
  latitude?: number | null;
  longitude?: number | null;
  is_default?: boolean;
};


/*
|--------------------------------------------------------------------------
| GET /api/account/addresses
|--------------------------------------------------------------------------
*/

export async function GET() {
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


    const response =
      await storefleetCustomerRequest(
        "/wp-json/storefleet/v1/customers/addresses",
        {
          method: "GET",
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
        status: 200,
      }
    );
  } catch (error) {
    console.error(
      "StoreFleet customer addresses list error:",
      error
    );


    return NextResponse.json(
      {
        success: false,
        message:
          "Unable to load delivery addresses.",
      },
      {
        status: 500,
      }
    );
  }
}


/*
|--------------------------------------------------------------------------
| POST /api/account/addresses
|--------------------------------------------------------------------------
*/

export async function POST(
  request: Request
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


    const body =
      (await request.json()) as AddressPayload;


    const response =
      await storefleetCustomerRequest(
        "/wp-json/storefleet/v1/customers/addresses",
        {
          method: "POST",
          body,
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
      "StoreFleet customer address create error:",
      error
    );


    return NextResponse.json(
      {
        success: false,
        message:
          "Unable to save delivery address.",
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