let port = null;
let reader = null;
let lineBuffer = "";
let lastWeight = null;
let sameCount = 0;
let weightFrozen = false; // Prevents ReferenceError crash

/* STATUS */
function setStatus(text, color = "#b91c1c") {
    const el = document.getElementById("connStatus");
    if (!el) return;
    el.innerText = text;
    el.style.color = color;
}

/* UPDATE LIVE WEIGHT UI */
function updateLiveWeight(weight) {
    if (weight === lastWeight) {
        sameCount++;
    } else {
        sameCount = 0;
    }

    lastWeight = weight;

    // Display weight on live display
    const el = document.getElementById("live_weight");
    if (el) {
        if (el.tagName === "INPUT" || el.tagName === "TEXTAREA") {
            el.value = weight;
        } else {
            el.innerText = weight;
        }
    }
}

/* CONNECT */
async function connectScale() {
    console.log("connectScale() started");

    try {
        port = await navigator.serial.requestPort();

        await port.open({
            baudRate: 9600,
            dataBits: 8,
            stopBits: 1,
            parity: "none",
            flowControl: "none"
        });

        lineBuffer = "";
        readScale();

        setStatus("CONNECTED", "#16a34a");

    } catch (e) {
        console.error(e);
        setStatus("DISCONNECTED", "#b91c1c");
    }
}

/* CONNECT DIRECT (DIALOG BUTTON) */
async function connectScaleDirect() {
    if (port) {
        console.log("Already connected");
        return;
    }

    try {
        let ports = await navigator.serial.getPorts();

        if (ports.length > 0) {
            console.log("Using remembered port...");
            port = ports[0];
        } else {
            console.log("Requesting new port...");
            port = await navigator.serial.requestPort();
        }

        await port.open({
            baudRate: 9600, // Matches AccessPort baud rate
            dataBits: 8,
            stopBits: 1,
            parity: "none",
            flowControl: "none"
        });

        console.log("PORT OPENED");
        setStatus("CONNECTED", "#16a34a");
        closeScaleDialog();

        lineBuffer = "";
        readScale();

    } catch (err) {
        console.error(err);
        alert("Connection failed: " + err.message);
        setStatus("DISCONNECTED", "#b91c1c");
    }
}

/* AUTO RECONNECT */
async function autoReconnect() {
    try {
        const ports = await navigator.serial.getPorts();
        if (ports.length > 0) {
            port = ports[0];
            await port.open({
                baudRate: 9600,
                dataBits: 8,
                stopBits: 1,
                parity: "none",
                flowControl: "none"
            });
            lineBuffer = "";

            readScale();
            setStatus("CONNECTED", "#16a34a");
        }
    } catch (e) {
        setStatus("DISCONNECTED", "#b91c1c");
    }
}

/* DISCONNECT */
async function disconnectScale() {
    try {
        if (reader) {
            await reader.cancel();
            reader.releaseLock();
            reader = null;
        }

        if (port) {
            await port.close();
            port = null;
        }

        console.log("PORT CLOSED");
    } catch (e) {
        console.error(e);
    }

    setStatus("DISCONNECTED", "#b91c1c");
}

/* READ DATA */
async function readScale() {
    const decoder = new TextDecoder();
    
    if (!port || !port.readable) return;
    reader = port.readable.getReader();

    try {
        while (true) {
            const { value, done } = await reader.read();
            if (done) break;

            const text = decoder.decode(value);
            console.log("RAW:", text);

            lineBuffer += text;

            let lines = lineBuffer.split(/\r?\n/);
            lineBuffer = lines.pop(); // keep incomplete part

            for (let line of lines) {
                processWeightLine(line);
            }
        }
    } catch (e) {
        console.error(e);
        setStatus("DISCONNECTED", "#b91c1c");
    } finally {
        if (reader) {
            reader.releaseLock();
            reader = null;
        }
    }
}
 
/* PARSE WEIGHT (DECIMAL SUPPORT) */
function processWeightLine(line) {
    if (!line) return;

    // Match numbers with optional decimal point (e.g., "00007.5" from "wn00007.5kg")
    let match = line.match(/[-+]?\d*\.?\d+/);

    if (!match) return;

    let weight = parseFloat(match[0]);

    if (!weightFrozen && !isNaN(weight)) {
        updateLiveWeight(weight);
    }
}

function openScaleDialog() {
    document.getElementById("scaleDialog").style.display = "block";
}

function closeScaleDialog() {
    document.getElementById("scaleDialog").style.display = "none";
}