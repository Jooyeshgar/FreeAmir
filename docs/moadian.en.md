---
title: "Moadian Setup Guide"
description: "How to set up and connect the Moadian Tax System in Amir"
---

# Setting Up the Moadian Tax System in Amir

To use the Moadian Tax System in Amir, you will need the following:

1. Moadian Username (Unique Tax Memory ID)
2. Private Key
3. Signature Certificate

The following sections explain how to obtain each of these items.

## Step 1: Create a Private Key and CSR

First, create a Certificate Signing Request (CSR).

Create a text file with the following content and replace the sample values with your company's information:

```ini
[req]
prompt = no
distinguished_name = dn

[dn]
CN = MyCompany [Stamp]
serialNumber = 14000405500
O = Non-Governmental
1.OU = Company Name
2.OU = Organizational Unit
3.OU = Organizational Unit
L = Tehran
ST = Tehran
C = IR
```

Then run the following OpenSSL command:

```bash
openssl req -new -newkey rsa:2048 -nodes \
-keyout private.key \
-out company.csr \
-config csr.conf
```

This command generates two files:

- `private.key` — Your private key
- `company.csr` — Your Certificate Signing Request (CSR)

> ⚠️ The `private.key` file is extremely important. Store it securely and keep a backup. If it is lost, the issued certificate cannot be used.

## Step 2: Request an Organizational Seal Certificate

1. Visit the GICA website.
2. From the right-hand menu, select **Register Electronic Certificate Request**.
3. Choose **Register Certificate Request via CSR**.
4. Select the certificate validity period (one year or two years).
5. Upload the CSR file created in the previous step.
6. Submit your request.

After submission, you will receive a receipt or referral letter.

## Step 3: Visit a Government Service Office

Take the required documents and the referral letter to an authorized government service office.

After your identity has been verified and the process is completed, your Organizational Seal Certificate will be issued.

You will typically receive a certificate file with a `.cer` or `.crt` extension.

## Step 4: Extract the Public Key

After receiving the certificate, extract the public key by running:

```bash
openssl x509 -pubkey -noout -in mystamp.cer > public.key
```

You now have the following files required by Amir:

- `private.key`
- `public.key`

## Step 5: Obtain Your Moadian Username

After receiving your certificate, sign in to the Moadian portal.

1. Open the **Unique Tax Memory IDs** section.
2. Create a new tax memory or select an existing one.
3. Associate your certificate with the selected tax memory.
4. Once the tax memory has been registered, the **Unique Tax Memory ID** will be displayed.

In Amir, this value is used as the **Moadian Username**.

> ⚠️ Each Tax Memory has its own unique identifier. Enter it exactly as it appears in the Moadian portal.

## Step 6: Configure Amir

Open your company's settings in Amir and enter the following information:

| Field | Value |
| --- | --- |
| Moadian Username | Unique Tax Memory ID |
| Private Key | Contents of the `private.key` file |
| Signature Certificate | Contents of the `.cer` or `.crt` certificate file |

Save the settings and test the Moadian connection.

If everything has been configured correctly, Amir will be able to submit electronic invoices to the Moadian Tax System.

## Important Notes

- Your private key represents your company's digital signature and must never be shared with unauthorized individuals.
- Always keep a secure backup of the `private.key` file.
- If the private key is lost or deleted, the issued certificate can no longer be used.
- The Moadian Username (Unique Tax Memory ID) must exactly match the value shown in the Moadian portal.
- Amir does not require direct access to a hardware token. Only the private key and the signature certificate are required.