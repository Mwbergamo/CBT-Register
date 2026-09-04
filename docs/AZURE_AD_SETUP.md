# Microsoft 365 / Entra ID (Azure AD) SSO setup

Associates sign in with their CodeBlue Microsoft 365 account — there's no
local password. This is a one-time setup in the Microsoft Entra admin
center (entra.microsoft.com), done by whoever has Azure AD admin rights for
CodeBlue's tenant.

## 1. Register the app

1. Go to **Entra ID > App registrations > New registration**.
2. Name it something like "CodeBlue Retail Register".
3. **Supported account types**: "Accounts in this organizational directory
   only (CodeBlue Technology only — Single tenant)". This is important —
   it's what keeps sign-in restricted to CodeBlue's own tenant rather than
   any Microsoft account.
4. **Redirect URI**: platform "Web", value:
   ```
   https://register.codebluetechnology.com/auth/azure/callback
   ```
   Add `http://localhost:8000/auth/azure/callback` too if you'll test
   locally before deploying.
5. Register.

## 2. Collect the IDs

On the app's **Overview** page, note:

- **Application (client) ID** → `AZURE_CLIENT_ID`
- **Directory (tenant) ID** → `AZURE_TENANT_ID`

## 3. Create a client secret

**Certificates & secrets > New client secret.** Copy the *value* (not the
secret ID) immediately — it's only shown once.

```
AZURE_CLIENT_SECRET=<the value>
```

## 4. API permissions

**API permissions > Add a permission > Microsoft Graph > Delegated
permissions**, add:

- `openid`
- `profile`
- `email`
- `User.Read` (usually included by default)

Click **Grant admin consent for CodeBlue Technology** so associates aren't
prompted to individually consent the first time they sign in.

## 5. Restrict who can use it

Single-tenant registration (step 1) already means only CodeBlue Microsoft
365 accounts can authenticate at all. To further restrict it to just the
associates who should have register access (rather than every employee):

1. **Entra ID > Enterprise applications**, find this app.
2. **Properties** → set **"Assignment required?"** to **Yes**.
3. **Users and groups** → assign the specific users or an Azure AD group
   (e.g. "Retail Associates") who should be able to sign in.

This is the actual access-control boundary — this app itself doesn't
maintain its own separate user allow-list; whoever Azure AD lets through
gets an account created on first sign-in
(`App\Http\Controllers\Auth\AzureLoginController::callback()`).

## 6. Set the redirect URI in `.env`

```
AZURE_CLIENT_ID=<application client id>
AZURE_CLIENT_SECRET=<client secret value>
AZURE_TENANT_ID=<directory tenant id>
AZURE_REDIRECT_URI=https://register.codebluetechnology.com/auth/azure/callback
```

`config/services.php` reads `tenant` from `AZURE_TENANT_ID` and passes it
straight to Socialite — never change this to `common` or `organizations`,
which would allow *any* Microsoft account (personal or any other
company's) to reach the sign-in screen.

## 7. Deactivating someone

If an associate leaves or shouldn't have access anymore, remove their
assignment in **Enterprise applications > Users and groups** (step 5) —
that blocks sign-in at the source. You can additionally flip
`is_active = false` on their row in this app's `users` table if you want
their existing session killed immediately rather than waiting for it to
expire, but Azure AD is the real gate.
