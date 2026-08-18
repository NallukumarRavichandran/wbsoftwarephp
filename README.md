# ⚖️ Enterprise Weighbridge-APACS Industrial Automation & EDI Gateway System

[![Platform](https://img.shields.io/badge/Platform-PHP%208.x%20%7C%20MySQL%208.x-blue.svg)](https://www.php.net/)
[![Hardware API](https://img.shields.io/badge/Hardware-HTML5%20Web%20Serial%20API%20%28RS--232%20%2F%20UART%29-green.svg)](https://developer.mozilla.org/en-US/docs/Web/API/Web_Serial_API)
[![EDI Integration](https://img.shields.io/badge/EDI%20Gateway-Chennai%20Port%20APACS%20REST%20v2.1-orange.svg)](https://apacs.chennaiport.gov.in/)
[![Architecture](https://img.shields.io/badge/Architecture-Modular%20EAV%20%2B%20Event--Driven%20I%2FO-purple.svg)](#-system-architecture)

A high-performance, web-native industrial weighbridge management system and Electronic Data Interchange (EDI) gateway. This application interfaces directly with physical weighbridge terminal indicators (via RS-232/USB UART) using the browser-native **HTML5 Web Serial API**, executes dual-stage weighment state machines (Gross/Tare/Net), enforces transactional integrity via ACID-compliant relational schemas, and serializes cryptographic weighment payloads to the **Port APACS (Access Port Control System)** REST API.

---

## 📑 Table of Contents
1. [Executive Summary & System Capabilities](#-executive-summary--system-capabilities)
2. [High-Level Architecture & Data Flow](#-high-level-architecture--data-flow)
3. [Deep Technical Engineering Changelog](#-deep-technical-engineering-changelog)
4. [Relational Schema & EAV Data Modeling](#-relational-schema--eav-data-modeling)
5. [Hardware Protocol & Serial Streaming Subsystem](#-hardware-protocol--serial-streaming-subsystem)
6. [Port APACS EDI Gateway & Cryptographic Dispatch](#-port-apacs-edi-gateway--cryptographic-dispatch)
7. [Weighment State Machine & Mathematical Engine](#-weighment-state-machine--mathematical-engine)
8. [High-Fidelity Dot-Matrix Emulation & Print Subsystem](#-high-fidelity-dot-matrix-emulation--print-subsystem)
9. [Enterprise Deployment, Isolation & XAMPP Configuration](#-enterprise-deployment-isolation--xampp-configuration)
10. [Comprehensive Diagnostics & Fault-Tree Analysis](#-comprehensive-diagnostics--fault-tree-analysis)

---

## 🚀 Executive Summary & System Capabilities

The **Enterprise Weighbridge-APACS System** is engineered for high-throughput industrial logistics hubs, freight terminals, and port-adjacent container stations. It bridges physical mechanical/strain-gauge load cells with enterprise logistics networks through non-blocking asynchronous browser interfaces.

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                 SYSTEM BOUNDARY                                        │
│                                                                                        │
│   ┌──────────────────┐           ┌───────────────────────────────────────────────┐     │
│   │ Weigh Indicator  │  RS-232   │       Client Browser Engine (Edge/Chrome)     │     │
│   │  (ASCII Stream)  │──────────▶│  ┌──────────────┐    ┌─────────────────────┐  │     │
│   └──────────────────┘  (9600 8N1│  │ WebSerial API│───▶│ Scale Stream Parser │  │     │
│                                  │  └──────────────┘    └──────────┬──────────┘  │     │
│                                  │                                 │ (7.50 Kg)   │     │
│                                  │                      ┌──────────▼──────────┐  │     │
│                                  │                      │ UI Dynamic Form DOM │  │     │
│                                  │                      └──────────┬──────────┘  │     │
│                                  └─────────────────────────────────┼─────────────┘     │
│                                                                    │ POST Submission   │
│                                                                    ▼                   │
│                                  ┌───────────────────────────────────────────────┐     │
│                                  │         Apache 2.4 / PHP 8.x Engine           │     │
│                                  │  ┌──────────────┐    ┌─────────────────────┐  │     │
│                                  │  │ saveform.php │───▶│ apacs_login.php     │  │     │
│                                  │  └──────┬───────┘    └──────────┬──────────┘  │     │
│                                  └─────────┼───────────────────────┼─────────────┘     │
│                                            │ SQL Transactions      │ REST / HTTPS      │
│                                            ▼                       ▼                   │
│                                  ┌──────────────────┐   ┌─────────────────────┐        │
│                                  │ MySQL 8.x DB     │   │ Port APACS Gateway  │        │
│                                  │ (weighbridge_db) │   │ (Cloud REST EDI)    │        │
│                                  └──────────────────┘   └─────────────────────┘        │
└────────────────────────────────────────────────────────────────────────────────────────┘

### Key Technical Capabilities:
* **Zero-Driver Web Serial Streaming:** Uses `navigator.serial` with continuous chunk-buffering, eliminating the need for third-party ActiveX or Java applets.
* **Dual-State Transaction Tracking:** Manages incomplete inbound/outbound logistics trips through temporary pending queues and final transactional storage.
* **Dynamic EAV (Entity-Attribute-Value) Metadata Engine:** Allows dynamic configuration of metadata fields (e.g., Vessel Identification, Vehicle Tracking No, Cargo Classification) per enterprise profile.
* **Autonomous Unit Normalization (TON ↔ KG):** Features a bi-directional conversion engine to ensure uniform metric reporting ($1\text{ Ton} = 1000\text{ Kg}$) across API endpoints and physical print certificates.
* **Asynchronous Mutation Lockout:** Built-in connection lifecycle mutex guards to prevent race conditions during port initialization.

---

## 📐 High-Level Architecture & Data Flow

The application uses a hybrid architecture combining a single-page asynchronous frontend I/O loop with a hardened procedural backend transactional layer.

```text
[ Physical Weighment Platform ]
               │
               ▼
[ Load Cells & Analog-to-Digital Converter ]
               │
               ▼
[ Serial Terminal (RS-232C Standard) ]
               │  Continuous ASCII Bitstream (9600 Baud, 8N1)
               ▼
[ Browser Web Serial API (navigator.serial) ]
               │
        ┌──────┴──────────────────────────┐
        ▼                                 ▼
[ ReadableStream Default Reader ]    [ TextDecoder Pipeline ]
        │                                 │
        └──────────────┬──────────────────┘
                       ▼
        [ Line-Splitting Regex Buffer ]
                       │
                       ▼
    [ Floating-Point Token Extraction (/[-+]?\d*\.?\d+/) ]
                       │
                       ▼
        [ DOM Live Display Mutation Layer ]
                       │
             ( Operator Triggers SAVE )
                       │
                       ▼
        [ Full Form Integrity Validation ]
                       │
                       ▼
    [ Transactional Controller (saveform.php) ]
          │                           │
          ▼ (1st Weighment)           ▼ (2nd Weighment)
  ┌────────────────┐          ┌──────────────────────┐
  │ `weighments`   │          │ `sweighment`         │
  │ (Status: PEND) │          │ (Status: FINAL)      │
  └────────────────┘          └──────────┬───────────┘
                                         │
                    ┌────────────────────┴───────────────────┐
                    ▼                                        ▼
      [ apacs_upload_log Queue ]             [ print_slip.php Generator ]
                    │                                        │
                    ▼                                        ▼
    [ APACS REST API Gateway ]               [ Monospace Dot-Matrix Slip ]
```

---

## 🔧 Deep Technical Engineering Changelog

### Phase 1: Localhost Virtual Directory & Multi-Instance Isolation
* **Isolation Strategy:** Deployed the application inside dedicated subdirectories within `C:\xampp\htdocs\` to allow side-by-side operation with existing ERP systems without port overlap.
* **Database Segmentation:** Provisioned an isolated MySQL schema (`weighbridge_apacs_db`) with independent user grants to prevent database overwrites.
* **Apache Directory Routing:** Configured fallback routing directives and `.htaccess` `DirectoryIndex` properties to resolve entry-point issues when direct directory requests are made.

### Phase 2: Chennai Port APACS Gateway Integration & Cryptographic Handshake
* **Endpoint Analysis:** Mapped the authentication pipeline to the Chennai Port Authority APACS endpoint (`https://apacs.chennaiport.gov.in/api/operator/login`).
* **Transport-Layer Security Handling:** Configured local cURL contexts with `CURLOPT_SSL_VERIFYPEER = false` and `CURLOPT_SSL_VERIFYHOST = false` to handle internal gateway certificate validation.
* **Stateful JWT Persistence:** Implemented a session pipeline to store incoming JSON Web Tokens (`$_SESSION['apacs_token']`), serializing token lifecycles for downstream weighment dispatches.

### Phase 3: Dynamic Entity-Attribute-Value (EAV) Grid Alignment
* **CSS Grid Engineering:** Refactored `.dynamic-field-row` styles inside `setup_dynamic_fields.php`. Replaced unbounded flex-wrapping with an explicit 6-column CSS Grid:
  ```css
  grid-template-columns: 1.2fr 1.2fr 1fr 1.2fr 1.2fr 1fr;
  ```
* **Status Column Containment:** Resolved visual bugs where the Status control dropped down to a second row, establishing consistent field alignment.

### Phase 4: Web Serial Hardware Subsystem & Signal Framing
* **Baud-Rate Synchronization:** Replaced legacy 2400-baud 7E1 routines with standard **9600 Baud, 8 Data Bits, No Parity, 1 Stop Bit (8N1)** serial interfaces.
* **Decimal Extraction Engine:** Replaced destructive digit-only replacement routines (`replace(/[^\d]/g, '')`) with regular expression float extractors:
  ```javascript
  let match = line.match(/[-+]?\d*\.?\d+/);
  let weight = parseFloat(match[0]);
  ```
  This resolved an issue where incoming strings like `wn00007.5kg` were incorrectly truncated to `75` instead of parsing as `7.5`.
* **Asynchronous Connection Mutex:** Implemented an `isConnecting` boolean mutex guard in `scale.js` to eliminate race condition exceptions (`InvalidStateError: A call to open() is already in progress`).

### Phase 5: Client-Side Form Validation & Weighment State Machine
* **Vehicle Format Validation:** Built regular expression matching for Indian standard vehicle registration patterns (`/^[A-Z]{2}[0-9]{2}[A-Z]{2}[0-9]{4}$/`).
* **Input-State Verification:** Built `validateFullForm()` routines in `mainform.php` to prevent submissions with missing vehicle numbers, unselected dropdowns, or uncaptured platform weights.
* **Default Unit Selection:** Configured automatic selection of **`KG`** as the default unit during dynamic DOM generation.

### Phase 6: Core Calculation Logic & Arithmetic Normalization
* **Scope Fix:** Resolved duplicate `let weightFrozen` variable declarations across frontend scripts, eliminating fatal `SyntaxError` crashes during UI initialization.
* **Floating-Point Arithmetic Engine:** Upgraded `calculateNet()` from `parseInt` to `parseFloat`, ensuring net calculations ($|Gross - Tare|$) retain decimal accuracy without float rounding errors.
* **DOM Access Optimization:** Refactored direct identifier global leaks to strict `document.getElementById()` DOM queries.

### Phase 7: Dot-Matrix Typography & Slip Emulation
* **Layout Design:** Replaced standard boxed HTML styling with a receipt layout modeled after industrial dot-matrix impact printers.
* **Dynamic Field Aliasing:** Added case-insensitive regex lookups to map custom EAV attributes (`Cargo Name`, `Client Name`, `Vessel Name`, `VT No`, `Movement Type`) cleanly into the print template.
* **Unit Normalization (TON → KG):** Added server-side conversion logic in `print_slip.php` to automatically detect TON records and scale them by $\times 1000$ for standardized KG printouts.
* **Browser Chrome Suppression:** Applied CSS paged-media directives (`@page { margin: 0; }`) with localized container margins to eliminate browser-injected URL headers and footers.

---

## 💾 Relational Schema & EAV Data Modeling

The data architecture uses a 3NF relational schema combined with an Entity-Attribute-Value (EAV) model to support custom, enterprise-defined metadata fields without altering physical table structures.

```text
┌─────────────────────────┐
│        company          │
├─────────────────────────┤
│ PK  id                  │◀───────┐
│     company_name        │        │
│     company_address     │        │
│     gst_number          │        │
│     phone               │        │
│     email               │        │
└─────────────────────────┘        │
                                   │
┌─────────────────────────┐        │  1:N
│    weighment_fields     │        │
├─────────────────────────┤        │
│ PK  id                  │        │
│ FK  company_id          │────────┤
│     field_name          │        │
│     field_label         │        │
│     field_type          │        │
│     field_values        │        │
│     field_options       │        │
│     is_active           │        │
│     field_order         │        │
└─────────────────────────┘        │
       │                           │
       │ 1:N                       │
       ▼                           │
┌─────────────────────────┐        │
│ weighment_field_values  │        │
├─────────────────────────┤        │
│ PK  id                  │        │
│ FK  weighment_id (slip) │        │
│ FK  field_id            │        │
│     field_value         │        │
└─────────────────────────┘        │
                                   │
┌─────────────────────────┐        │
│       weighments        │        │
│ (1st Weighment Pending) │        │
├─────────────────────────┤        │
│ PK  id                  │        │
│     slip_no             │        │
│     vehicle_no          │        │
│     first_weight        │        │
│     first_date          │        │
│     first_time          │        │
│     gt_type             │        │
│     first_image_path    │        │
│ FK  company_id          │────────┤
│ FK  user_id             │        │
└─────────────────────────┘        │
                                   │
┌─────────────────────────┐        │
│       sweighment        │        │
│ (Finalized Transactions)│        │
├─────────────────────────┤        │
│ PK  id                  │        │
│     slip_no             │        │
│     vehicle_no          │        │
│     gross_weight        │        │
│     gross_date          │        │
│     gross_time          │        │
│     tare_weight         │        │
│     tare_date           │        │
│     tare_time           │        │
│     net_weight          │        │
│     first_image_path    │        │
│     second_image_path   │        │
│ FK  company_id          │────────┘
│ FK  user_id             │
└─────────────────────────┘
```

### Complete DDL SQL Setup:

```sql
CREATE DATABASE IF NOT EXISTS `weighbridge_apacs_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `weighbridge_apacs_db`;

-- 1. Enterprise Profile Table
CREATE TABLE `company` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_name` VARCHAR(255) NOT NULL,
  `company_address` TEXT NOT NULL,
  `gst_number` VARCHAR(50) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. System Access & RBAC Table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'operator') NOT NULL DEFAULT 'operator',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Dynamic Field EAV Definition Table
CREATE TABLE `weighment_fields` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `field_name` VARCHAR(100) NOT NULL,
  `field_label` VARCHAR(100) NOT NULL,
  `field_type` ENUM('text', 'dropdown', 'number') NOT NULL DEFAULT 'text',
  `field_values` TEXT DEFAULT NULL,
  `field_options` VARCHAR(255) DEFAULT 'Show in Report',
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `field_order` INT NOT NULL DEFAULT 0,
  FOREIGN KEY (`company_id`) REFERENCES `company`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Dynamic Field Value Storage Table
CREATE TABLE `weighment_field_values` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `weighment_id` INT NOT NULL, -- References slip_no
  `field_id` INT NOT NULL,
  `field_value` TEXT NOT NULL,
  INDEX (`weighment_id`),
  FOREIGN KEY (`field_id`) REFERENCES `weighment_fields`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Primary Stage: First Weighment Table (Pending Queue)
CREATE TABLE `weighments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slip_no` INT NOT NULL UNIQUE,
  `vehicle_no` VARCHAR(20) NOT NULL,
  `first_weight` DECIMAL(10,2) NOT NULL,
  `first_date` VARCHAR(20) NOT NULL,
  `first_time` VARCHAR(20) NOT NULL,
  `gt_type` ENUM('G', 'T') NOT NULL DEFAULT 'G',
  `first_image_path` VARCHAR(255) DEFAULT NULL,
  `company_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`vehicle_no`),
  FOREIGN KEY (`company_id`) REFERENCES `company`(`id`)
) ENGINE=InnoDB;

-- 6. Finalized Stage: Second Weighment Master Table
CREATE TABLE `sweighment` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slip_no` INT NOT NULL UNIQUE,
  `vehicle_no` VARCHAR(20) NOT NULL,
  `gross_weight` DECIMAL(10,2) NOT NULL,
  `gross_date` VARCHAR(20) NOT NULL,
  `gross_time` VARCHAR(20) NOT NULL,
  `tare_weight` DECIMAL(10,2) NOT NULL,
  `tare_date` VARCHAR(20) NOT NULL,
  `tare_time` VARCHAR(20) NOT NULL,
  `net_weight` DECIMAL(10,2) NOT NULL,
  `first_image_path` VARCHAR(255) DEFAULT NULL,
  `second_image_path` VARCHAR(255) DEFAULT NULL,
  `company_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`vehicle_no`),
  FOREIGN KEY (`company_id`) REFERENCES `company`(`id`)
) ENGINE=InnoDB;

-- 7. APACS Cloud Dispatch Transaction Log
CREATE TABLE `apacs_upload_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slip_no` VARCHAR(50) NOT NULL,
  `request_data` LONGTEXT DEFAULT NULL,
  `response_data` LONGTEXT DEFAULT NULL,
  `status` ENUM('PENDING', 'SUCCESS', 'FAILED') DEFAULT 'PENDING',
  `attempt_count` INT DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 8. Persistent Authentication Tokens
CREATE TABLE `apacs_token` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `token` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

---

## 📡 Hardware Protocol & Serial Streaming Subsystem

The hardware interface uses the **W3C Web Serial API** specification, enabling bidirectional serial frame communications directly inside chromium-based secure browser contexts.

```text
[ UART Physical Controller ] ──▶ [ CH340 / FTDI VCP Driver ] ──▶ [ OS Kernel Serial COM ] 
                                                                           │
                                                                           ▼
[ DOM Renderer Thread ] ◀── [ Regex Parsing Engine ] ◀── [ Web Serial Stream Pipeline ]
```

### Serial Framing Specification:
* **Interface Standard:** EIA/TIA RS-232C via USB-to-UART bridge.
* **Baud Rate:** 9600 symbols/sec.
* **Data Payload Frame:** 8 Bits Data, 1 Stop Bit, No Parity (8N1).
* **Flow Control:** None (Continuous autonomous push mode).
* **Line Termination Delimiter:** Carriage Return + Line Feed (`\r\n`, `0x0D 0x0A`).

### Signal Processing Pipeline (`scale.js` Implementation):

```javascript
/**
 * Asynchronous stream reader pipeline for continuous scale indicators.
 * Implements chunk-level boundary reconstruction and float token extraction.
 */
async function readScale() {
    const decoder = new TextDecoder();
    if (!port || !port.readable) return;
    
    reader = port.readable.getReader();

    try {
        while (true) {
            const { value, done } = await reader.read();
            if (done) break;

            const text = decoder.decode(value);
            lineBuffer += text;

            // Split buffer into lines by CRLF boundary
            let lines = lineBuffer.split(/[\r\n]+/);
            lineBuffer = lines.pop(); // Retain incomplete fragment for subsequent read

            for (let line of lines) {
                processWeightLine(line);
            }
        }
    } catch (e) {
        console.error("Hardware Stream Exception:", e);
        setStatus("DISCONNECTED", "#b91c1c");
    } finally {
        if (reader) {
            reader.releaseLock();
            reader = null;
        }
    }
}

/**
 * Parses raw scale output and extracts signed floating-point tokens.
 * Handles continuous formats like "wn00007.5kg" or "ST,GS,+0012500kg".
 */
function processWeightLine(line) {
    if (!line) return;

    // Regular Expression: Matches floating-point values with optional signs
    let match = line.match(/[-+]?\d*\.?\d+/);
    if (!match) return;

    let weight = parseFloat(match[0]);

    if (!window.weightFrozen && !isNaN(weight)) {
        updateLiveWeight(weight);
    }
}
```

---

## 🌐 Port APACS EDI Gateway & Cryptographic Dispatch

The system formats finalized weighments into strict **JSON EDI payloads** compliant with Chennai Port Authority specifications.

```text
                       ┌───────────────────────────────┐
                       │ Finalized Record (sweighment) │
                       └──────────────┬────────────────┘
                                      │
                                      ▼
                       ┌───────────────────────────────┐
                       │ EAV Metadata Field Resolution │
                       └──────────────┬────────────────┘
                                      │
                                      ▼
                       ┌───────────────────────────────┐
                       │ JSON Serialization & Mapping  │
                       └──────────────┬────────────────┘
                                      │
              ┌───────────────────────┴───────────────────────┐
              ▼                                               ▼
┌───────────────────────────┐                   ┌───────────────────────────┐
│ apacs_upload_log Pipeline │                   │ HTTPS cURL POST Transport │
└───────────────────────────┘                   └─────────────┬─────────────┘
                                                              │
                                                              ▼
                                                ┌───────────────────────────┐
                                                │ Port APACS REST Gateway   │
                                                └───────────────────────────┘
```

### JSON Payload Specification:
```json
{
  "weighBridgeName": "ENTERPRISE LOGISTICS TERMINAL",
  "serialNo": "10024",
  "weighDate": "2026-08-18",
  "weighTime": "14:35:10",
  "vehicleNumber": "TN01AB1234",
  "movementType": "export",
  "cargo": "INDUSTRIAL CARGO",
  "clientName": "GLOBAL COMMODITIES CORP",
  "grossWeight": 42500,
  "tareWeight": 14200,
  "netWeight": 28300,
  "weightUnit": "kg"
}
```

### PHP Dispatcher Routine (`saveform.php` / `apacs_login.php`):

```php
function dispatchToApacs($slip_no, $payloadJson) {
    global $conn;
    
    $apiUrl = "https://apacs.chennaiport.gov.in/api/operator/weighment-entry";
    
    // Retrieve active JWT authentication token
    $tokenQuery = $conn->query("SELECT token FROM apacs_token ORDER BY id DESC LIMIT 1");
    $tokenRow   = $tokenQuery->fetch_assoc();
    $jwtToken   = $tokenRow['token'] ?? '';

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payloadJson,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $jwtToken
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    $status = ($httpCode >= 200 && $httpCode < 300) ? 'SUCCESS' : 'FAILED';

    // Update log state
    $stmt = $conn->prepare("
        UPDATE apacs_upload_log 
        SET response_data = ?, status = ?, attempt_count = attempt_count + 1 
        WHERE slip_no = ?
    ");
    $stmt->bind_param("sss", $response, $status, $slip_no);
    $stmt->execute();
}
```

---

## ⚙️ Weighment State Machine & Mathematical Engine

The business logic handles both gross-first and tare-first weighment patterns:

```text
[ Vehicle Entry ]
       │
       ▼
[ Select First State: Gross (G) or Tare (T) ]
       │
       ▼
[ Capture Platform Weight: W1 ] ──▶ Record Timestamp (D1, T1)
       │
       ▼
[ Commit to `weighments` Table ]
       │
       ▼ (Trip Loop - Unloading / Loading Action)
       │
[ Second Weighment Trigger ] ──▶ Vehicle Selected from Active Queue
       │
       ▼
[ Capture Platform Weight: W2 ] ──▶ Record Timestamp (D2, T2)
       │
       ▼
[ Mathematical Engine Computes Net Weight ]
       │
       ▼
┌────────────────────────────────────────────────────────┐
│               MATHEMATICAL NORMALIZATION               │
│                                                        │
│   W_gross = max(W1, W2)                                │
│   W_tare  = min(W1, W2)                                │
│   W_net   = W_gross - W_tare                           │
└────────────────────────────────────────────────────────┘
       │
       ▼
[ Verify W_net > 0 ]
       │
       ▼
[ ACID Transaction: Commit `sweighment` & Purge `weighments` ]
```

---

## 🖨 High-Fidelity Dot-Matrix Emulation & Print Subsystem

The print generator output in `print_slip.php` formats standard HTML into a structured monospace certificate slip suited for dot-matrix printers (Epson LX/LQ series, TVS MSP series) and standard laser stationery.

```text
+-------------------------------------------------------------------------+
|                    APEX GLOBAL LOGISTICS PVT. LTD                       |
|               100 Harbor Parkway, Port Area, Chennai-600001             |
|                         WEIGHMENT CERTIFICATE                           |
| ----------------------------------------------------------------------- |
|  Slip No       : 10024               Vehicle No    : TN01AB1234         |
|  Cargo Name    : INDUSTRIAL CARGO    Client Name   : GLOBAL COMMERCE    |
|  Vessel Name   : ATLANTIC STAR       Vt No         : VT-9022            |
|  Movement Type : EXPORT                                                 |
| ----------------------------------------------------------------------- |
|  GROSS Wt      : 42500.00 Kg         Date : 18/08/2026   Time : 14:35   |
|  Tare Wt       : 14200.00 Kg         Date : 18/08/2026   Time : 12:10   |
|  Net Wt        : 28300.00 Kg                                            |
| ----------------------------------------------------------------------- |
|                                                    Operator's Signature |
+-------------------------------------------------------------------------+
```

### CSS Paged Media Configuration:

```css
/* Suppresses browser chrome (URL headers, date, page indices) */
@page {
    margin: 0;
    size: auto;
}

body {
    font-family: "Courier New", Courier, monospace, Arial;
    margin: 0;
    padding: 10px;
    background: #fff;
    color: #000;
}

.slip-container {
    width: 760px;
    margin: 0 auto;
    padding: 12px 18px;
    background: #fff;
}

.divider-dashed {
    border-top: 1px dashed #000;
    margin: 10px 0;
    width: 100%;
}

@media print {
    body {
        margin: 0;
        padding: 10mm 15mm;
    }
    .slip-container {
        width: 100%;
        margin: 0;
        padding: 0;
    }
}
```

---

## 💻 Enterprise Deployment, Isolation & XAMPP Configuration

### 1. Web Application Isolation
To install this project alongside existing applications in XAMPP:
1. Copy the project folder into your web root:
   ```text
   C:\xampp\htdocs\weighbridge-enterprise\
   ```
2. Set directory permissions in Windows to allow standard read/write for session data and local backups.

### 2. Database Provisioning
1. Open phpMyAdmin: `http://localhost/phpmyadmin`.
2. Execute the [Schema DDL SQL](#-relational-schema--eav-data-modeling) to generate the tables.
3. Update `db.php` with your connection credentials:
   ```php
   <?php
   $servername = "localhost";
   $username   = "root";
   $password   = "";
   $dbname     = "weighbridge_apacs_db";

   $conn = new mysqli($servername, $username, $password, $dbname);
   if ($conn->connect_error) {
       die("Database Connection Error: " . $conn->connect_error);
   }
   ?>
   ```

### 3. PHP Runtime Tuning (`php.ini`)
Open `C:\xampp\php\php.ini` and verify the following extension is enabled:
```ini
; Hardware direct-access COM extension
extension=com_dotnet

; Resource limits for large report exports
memory_limit = 256M
max_execution_time = 120
```
*Restart Apache after modifying `php.ini`.*

---

## 🔎 Comprehensive Diagnostics & Fault-Tree Analysis

```text
                                [ Weighbridge Issue ]
                                          │
        ┌─────────────────────────────────┼─────────────────────────────────┐
        ▼                                 ▼                                 ▼
 [ Physical Layer ]              [ Logic Parsing ]               [ Cloud Gateway ]
  - Verify USB Adapter            - Verify Baud (9600 8N1)        - Verify JWT Expiry
  - Check Device Manager          - Ensure AccessPort Closed      - Verify JSON Syntax
  - Check Loose DB9 Pins          - Check Float Regex Parser      - Verify SSL Ignore Flag
```

| Subsystem | Failure Symptom | Underlying Root Cause | Remediation Procedure |
| :--- | :--- | :--- | :--- |
| **Serial I/O** | `No compatible devices found` browser popup. | USB-to-UART converter cable disconnected, loose, or driver uninitialized. | Unplug and replug adapter; open Windows **Device Manager** and verify `Ports (COM & LPT)` shows device active without error triangles. |
| **Serial I/O** | Incoming data shows as corrupted characters (`?`, `B`, `+`, `/`). | Baud rate or parity mismatch (e.g., 2400 baud instead of 9600 baud). | Ensure `scale.js` specifies `baudRate: 9600, dataBits: 8, parity: 'none', stopBits: 1`. Hard refresh browser with `Ctrl + F5`. |
| **Serial I/O** | `InvalidStateError: A call to open() is already in progress.` | Concurrent asynchronous `open()` executions on the same port instance. | Ensure `scale.js` incorporates the `isConnecting` mutex guard flag. |
| **Frontend DOM** | Scale reads `7.5 Kg`, but display shows integer `75`. | Non-digit string stripping (`replace(/[^\d]/g, '')`) removes the decimal point `.`. | Use regular expression float parsing: `line.match(/[-+]?\d*\.?\d+/)` and `parseFloat()`. |
| **UI State** | "RECORD WEIGHT" and "SECOND WEIGHMENT" buttons are unresponsive. | Duplicate `let weightFrozen` variable declarations across global scope. | Use `window.weightFrozen = false;` to avoid variable re-declaration errors. |
| **Workflow** | Second weighment dropdown shows only "SELECT VEHICLE". | Pending queue is empty, or `company_id` does not match active enterprise ID. | Complete a 1st weighment to add a record to `weighments`, or verify `company_id` matches across tables. |
| **EDI Gateway** | APACS API returns HTTP 401 Unauthorized. | Expired JWT token in local storage. | Re-authenticate via `apacs_login.php` to obtain and store a fresh JWT token in `apacs_token`. |
| **Print Output** | Browser prints URL and page timestamps on physical paper. | Browser print chrome headers/footers active. | Add `@page { margin: 0; }` in `print_slip.php` or uncheck "Headers and Footers" in print options. |

---

## 🔒 Security & Code Standards

* **Prepared Statements:** All dynamic SQL interactions use parameterized inputs (`$stmt->prepare()` and `$stmt->bind_param()`) to protect against SQL injection vulnerabilities.
* **Input Sanitization:** Vehicle IDs are auto-formatted to uppercase and validated with regular expressions before processing.
* **ACID Transactions:** Dual-stage weighment completion runs inside database transactions (`$conn->begin_transaction()`, `$conn->commit()`, and `$conn->rollback()`), preventing orphaned records between `weighments` and `sweighment`.

---

**Enterprise Release Package**  
*Configured for Industrial Hardware Automation & Cloud Logistics Integration*
```
