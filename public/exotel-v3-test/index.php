<!DOCTYPE html>
<html>
<head>
    <title>Exotel WebRTC Test</title>
</head>
<body>

<h2>📞 Exotel Browser Calling Test</h2>

<button onclick="makeCall()">Call Customer</button>
<button onclick="hangupCall()">Hangup</button>

<p id="status">Status: Not connected</p>

<script src="https://webrtc.exotel.com/js/exotel-webrtc.js"></script>

<script>
let client;
let currentCall = null;

// 🔹 INIT CLIENT
client = new ExotelWebRTC({
    username: "jayasurr9179f2a0",  // your agent login
    password: "Insight@1234",
    domain: "insighthcm5m.exotel.in"
});

// 🔹 REGISTER AGENT
client.register()
.then(() => {
    document.getElementById("status").innerText = "✅ Connected to Exotel";
    console.log("Connected");
})
.catch(err => {
    console.error(err);
    document.getElementById("status").innerText = "❌ Connection failed";
});

// 🔹 INCOMING CALL
client.onIncomingCall = function(call) {
    console.log("📞 Incoming call");
    currentCall = call;

    document.getElementById("status").innerText = "📞 Incoming call...";

    // Auto answer (for testing)
    call.answer();
};

// 🔹 CALL CONNECTED
client.onCallConnected = function(call) {
    console.log("✅ Call connected");
    document.getElementById("status").innerText = "✅ Call connected";
};

// 🔹 CALL ENDED
client.onCallEnded = function() {
    console.log("❌ Call ended");
    document.getElementById("status").innerText = "❌ Call ended";
};

// 🔹 OUTGOING CALL
function makeCall() {
    let number = "+916383702482"; // test number
    currentCall = client.call(number);

    document.getElementById("status").innerText = "📲 Calling " + number;
}

// 🔹 HANGUP
function hangupCall() {
    if (currentCall) {
        currentCall.hangup();
    }
}
</script>

</body>
</html>