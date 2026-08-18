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



Model 4:08 PM
Here is the completely fixed, properly formatted README.md file.
The broken ASCII diagrams, SQL schemas, PHP/JavaScript code blocks, and badges are now correctly wrapped inside GitHub-Flavored Markdown (GFM) fenced code blocks (```text, ```sql, ```javascript, etc.) so GitHub will render the diagrams, code, and tables cleanly.
code
Markdown
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
Key Technical Capabilities:
Zero-Driver Web Serial Streaming: Uses navigator.serial with continuous chunk-buffering, eliminating the need for third-party ActiveX or Java applets.
Dual-State Transaction Tracking: Manages incomplete inbound/outbound logistics trips through temporary pending queues and final transactional storage.
Dynamic EAV (Entity-Attribute-Value) Metadata Engine: Allows dynamic configuration of metadata fields (e.g., Vessel Identification, Vehicle Tracking No, Cargo Classification) per enterprise profile.
Autonomous Unit Normalization (TON ↔ KG): Features a bi-directional conversion engine to ensure uniform metric reporting (
1
 Ton
=
1000
 Kg
1 Ton=1000 Kg
) across API endpoints and physical print certificates.
Asynchronous Mutation Lockout: Built-in connection lifecycle mutex guards to prevent race conditions during port initialization.
📐 High-Level Architecture & Data Flow
The application uses a hybrid architecture combining a single-page asynchronous frontend I/O loop with a hardened procedural backend transactional layer.

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
    
