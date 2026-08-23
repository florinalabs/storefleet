import { cookies } from "next/headers";

export const CUSTOMER_SESSION_COOKIE =
  "storefleet_customer_session";

const DEFAULT_SESSION_MAX_AGE =
  7 * 24 * 60 * 60;

type StoreFleetRequestOptions = {
  method?:
    | "GET"
    | "POST"
    | "PATCH"
    | "DELETE";

  body?: unknown;

  sessionToken?: string | null;
};

type StoreFleetSession = {
  token: string;
  expires_in?: number;
};

type StoreFleetAuthResponse = {
  success?: boolean;
  customer?: unknown;
  session?: StoreFleetSession;
  session_token?: string;
  expires_in?: number;
  [key: string]: unknown;
};


/*
|--------------------------------------------------------------------------
| Environment
|--------------------------------------------------------------------------
*/

function getWordPressUrl(): string {
  const value =
    process.env.WORDPRESS_URL;

  if (!value) {
    throw new Error(
      "WORDPRESS_URL is not configured."
    );
  }

  return value.replace(/\/+$/, "");
}


function getInternalApiKey(): string {
  const value =
    process.env.STOREFLEET_INTERNAL_API_KEY;

  if (!value) {
    throw new Error(
      "STOREFLEET_INTERNAL_API_KEY is not configured."
    );
  }

  return value;
}


/*
|--------------------------------------------------------------------------
| WordPress API Request
|--------------------------------------------------------------------------
*/

export async function storefleetCustomerRequest(
  endpoint: string,
  options: StoreFleetRequestOptions = {}
): Promise<Response> {
  const {
    method = "GET",
    body,
    sessionToken,
  } = options;

  const headers =
    new Headers();

  headers.set(
    "Accept",
    "application/json"
  );

  headers.set(
    "X-StoreFleet-Key",
    getInternalApiKey()
  );

  if (body !== undefined) {
    headers.set(
      "Content-Type",
      "application/json"
    );
  }

  if (sessionToken) {
    headers.set(
      "Authorization",
      `Bearer ${sessionToken}`
    );
  }

  return fetch(
    `${getWordPressUrl()}${endpoint}`,
    {
      method,
      headers,
      body:
        body !== undefined
          ? JSON.stringify(body)
          : undefined,
      cache: "no-store",
    }
  );
}


/*
|--------------------------------------------------------------------------
| Session Response Helpers
|--------------------------------------------------------------------------
*/

export function extractSession(
  payload: StoreFleetAuthResponse
): StoreFleetSession | null {
  if (
    payload.session &&
    typeof payload.session === "object" &&
    typeof payload.session.token === "string" &&
    payload.session.token.length > 0
  ) {
    return {
      token:
        payload.session.token,

      expires_in:
        typeof payload.session.expires_in ===
        "number"
          ? payload.session.expires_in
          : undefined,
    };
  }

  if (
    typeof payload.session_token ===
      "string" &&
    payload.session_token.length > 0
  ) {
    return {
      token:
        payload.session_token,

      expires_in:
        typeof payload.expires_in ===
        "number"
          ? payload.expires_in
          : undefined,
    };
  }

  return null;
}


/*
|--------------------------------------------------------------------------
| HttpOnly Cookie
|--------------------------------------------------------------------------
*/

export async function setCustomerSessionCookie(
  session: StoreFleetSession
): Promise<void> {
  const cookieStore =
    await cookies();

  const maxAge =
    session.expires_in &&
    session.expires_in > 0
      ? session.expires_in
      : DEFAULT_SESSION_MAX_AGE;

  cookieStore.set(
    CUSTOMER_SESSION_COOKIE,
    session.token,
    {
      httpOnly: true,
      sameSite: "lax",
      secure:
        process.env.NODE_ENV ===
        "production",
      path: "/",
      maxAge,
    }
  );
}


export async function getCustomerSessionToken():
  Promise<string | null> {
  const cookieStore =
    await cookies();

  return (
    cookieStore.get(
      CUSTOMER_SESSION_COOKIE
    )?.value ?? null
  );
}


export async function clearCustomerSessionCookie():
  Promise<void> {
  const cookieStore =
    await cookies();

  cookieStore.set(
    CUSTOMER_SESSION_COOKIE,
    "",
    {
      httpOnly: true,
      sameSite: "lax",
      secure:
        process.env.NODE_ENV ===
        "production",
      path: "/",
      maxAge: 0,
      expires: new Date(0),
    }
  );
}