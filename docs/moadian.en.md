---
title: "Moadian Setup Guide"
description: "How to set up and connect the Moadian tax system in Amir"
---

# Setting up Moadian in Amir

To use the Moadian system in Amir, you need three things:

1. Moadian username (unique tax memory identifier)
2. Private key
3. Signaure Certificate

Below are the steps to obtain these.


## Step 1: Create a private key and CSR file

First, create a CSR (Certificate Signing Request) file.

Create a text file with the following content and replace the values with your company details:

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

Then run the following command using OpenSSL:

```bash
openssl req -new -newkey rsa:2048 -nodes \
-keyout private.key \
-out company.csr \
-config csr.conf
```

Two files will be created:

* `private.key` — private key
* `company.csr` — certificate signing request (CSR)

> ⚠️ The `private.key` file is extremely important. Store it securely and keep a backup.


## Step 2: Request an organization stamp certificate

1. Go to the [GICA](https://www.gica.ir) website.
2. Click "Register Electronic Certificate Request" from the right menu.
3. Select "Register certificate request via CSR".
4. Choose the certificate type (one-year or two-year).
5. Upload the CSR file created in the previous step.
6. Submit the request.

After submitting, you will receive a receipt or referral letter.


## Step 3: Visit a government service office

Visit one of the government service offices with the required documents and the referral letter.

After identity verification and process completion, an organization stamp certificate will be issued.

You will receive a certificate file with the `.cer` or `.crt` extension.


## Step 4: Extract the public key

After receiving the certificate file, extract the public key:

```bash
openssl x509 -pubkey -noout -in mystamp.cer > public.key
```

You now have the public key file:
* `public.key`


## Step 5: Obtain the Moadian username

After receiving the certificate, log in to the Moadian portal.

1. Go to the "Unique Tax Memory Identifiers" section.
2. Create a new tax memory or select an existing one.
3. Attach the received certificate to the tax memory.
4. After registering the tax memory, the "Unique Tax Memory Identifier" will be displayed.

In Amir, this identifier is used as the **Moadian username**.

> ⚠️ The unique tax memory identifier is unique to each tax memory and must be entered exactly as shown in the Moadian portal.


## Step 6: Configure in Amir

In the company settings section of Amir, enter the following:

| Field | Value |
|---|---|
| Moadian username | Unique tax memory identifier |
| Private key | Contents of `private.key` file |
| Signaure Certificate | Contents of `.cer` or `.crt` file |

After saving the settings, test the Moadian connection.

If the information is correct, the application will be able to send electronic invoices to the Moadian system.


## Important notes

* The private key is equivalent to your company's digital signature and should not be accessible to unauthorized individuals.
* Keep a backup of the `private.key` file.
* If the private key is deleted or lost, the issued certificate cannot be used.
* The unique tax memory identifier must be entered exactly as registered in the Moadian system.
* In Amir, there is no need to use a hardware token directly; only public and private keys are used.
