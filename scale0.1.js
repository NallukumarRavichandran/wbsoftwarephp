let port = null;
let reader = null;
let lineBuffer = "";
let lastWeight = null;
let sameCount = 0;


/* STATUS */
function setStatus(text, color = "#b91c1c") {
    const el = document.getElementById("connStatus");
    if (!el) return;
    el.innerText = text;
    el.style.color = color;
}
function updateLiveWeight(weight) {

    if (weight === lastWeight) {
        sameCount++;
    } else {
        sameCount = 0;
    }

    lastWeight = weight;

    // accept after 3 identical readings
    if (sameCount >= 2) {
        document.getElementById("live_weight").innerText = weight;
    }
}


/* CONNECT */

async function connectScale() {

    // 🔴 TEST 1 — Did function run?
    alert("connectScale() called");

    // 🔴 TEST 2 — Change menu status text
    setStatus("CONNECT FUNCTION HIT", "orange");

    console.log("connectScale() started");

    try {

        alert("Requesting serial port now...");

        port = await navigator.serial.requestPort();

        alert("Port selection done");

        await port.open({
            baudRate: 2400,
            dataBits: 8,
            stopBits: 1,
            parity: "none",
		    flowControl: "none"

        });

        alert("Port opened successfully");

        lineBuffer = "";
        readScale();

        setStatus("CONNECTED", "#16a34a");

    } catch (e) {

        alert("ERROR: " + e.message);
        console.error(e);

        setStatus("DISCONNECTED", "#b91c1c");
    }
}

async function connectScaleDirect() {
	
if (port) {
    console.log("Already connected");
    return;
}

    try {

        // Check if we already have permission
        let ports = await navigator.serial.getPorts();

        if (ports.length > 0) {
            console.log("Using remembered port...");
            port = ports[0];
        }
        else {
            console.log("Requesting new port...");
            port = await navigator.serial.requestPort();
        }

        await port.open({
            baudRate: 2400,
            dataBits: 7,
            stopBits: 1,
            parity: "even",
            flowControl: "none"
        });

        console.log("PORT OPENED");
        setStatus("CONNECTED", "#16a34a");

        lineBuffer = "";
        readScale();

    }
    catch (err) {
        console.error(err);
        alert("Connection failed: " + err.message);
    }
}

/* AUTO RECONNECT */
async function autoReconnect() {
    try {
        const ports = await navigator.serial.getPorts();
        if (ports.length > 0) {
            port = ports[0];
            await port.open({
                baudRate: 2400,
                dataBits: 7,
                stopBits: 1,
                parity: "even",
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
    }
    catch (e) {
        console.error(e);
    }

    setStatus("DISCONNECTED", "#b91c1c");
}


/* READ DATA */
async function readScale() {

    const decoder = new TextDecoder();
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
    }
}


/* PARSE */
function processWeightLine(line) {

    // remove all non-numeric characters
    let cleaned = line.replace(/[^\d]/g, '');

    if (cleaned.length === 0) return;

    let weight = parseInt(cleaned, 10);

    if (!weightFrozen) {
        updateLiveWeight(weight);
    }
}



function openScaleDialog() {
    document.getElementById("scaleDialog").style.display = "block";
}

function closeScaleDialog() {
    document.getElementById("scaleDialog").style.display = "none";
}




