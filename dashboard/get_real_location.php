<?php
session_start();
require_once 'ip_address_utils.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Access denied']);
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌍 Get Real Location - Japanese Learning Platform</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .method { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .btn { padding: 10px 20px; margin: 5px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; display: inline-block; cursor: pointer; border: none; }
        .btn:hover { background: #0056b3; }
        .location-display { font-size: 18px; font-weight: bold; margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .ip-display { font-family: monospace; font-size: 14px; padding: 8px; background: #e9ecef; border-radius: 4px; margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌍 Get Your Real Location</h1>
        <p>Currently showing localhost because you're accessing from ::1. Here are ways to get your real location:</p>

        <!-- Method 1: Browser Geolocation -->
        <div class="method info">
            <h2>Method 1: 📍 Browser Geolocation (Most Accurate)</h2>
            <p>This uses your browser's GPS/WiFi location services:</p>
            <button onclick="getBrowserLocation()" class="btn">🎯 Get My Location</button>
            <div id="browserLocation" style="margin-top: 15px;"></div>
        </div>

        <!-- Method 2: Public IP Detection -->
        <div class="method warning">
            <h2>Method 2: 🌐 Access via Public IP</h2>
            <p>To get your real location through IP geolocation, you need to:</p>
            <ol>
                <li><strong>Access from external network:</strong> Use your mobile hotspot or different network</li>
                <li><strong>Use your public IP:</strong> Find your public IP and access via that</li>
                <li><strong>Deploy to live server:</strong> Access from a live domain instead of localhost</li>
            </ol>
            
            <h3>🔍 Your Current Network Info:</h3>
            <div class="ip-display">
                <strong>Local IP:</strong> <?php echo IPAddressUtils::getRealIPAddress(); ?><br>
                <strong>Status:</strong> Localhost (no geolocation possible)
            </div>
        </div>

        <!-- Method 3: Manual Override -->
        <div class="method success">
            <h2>Method 3: ✏️ Manual Location Override</h2>
            <p>Set your location manually for testing:</p>
            <form onsubmit="setManualLocation(event)">
                <div style="margin: 10px 0;">
                    <label>City: <input type="text" id="manualCity" placeholder="Quezon City" style="padding: 8px; margin-left: 10px;"></label>
                </div>
                <div style="margin: 10px 0;">
                    <label>Country: <input type="text" id="manualCountry" placeholder="Philippines" style="padding: 8px; margin-left: 10px;"></label>
                </div>
                <button type="submit" class="btn">📍 Set My Location</button>
            </form>
            <div id="manualLocation" style="margin-top: 15px;"></div>
        </div>

        <!-- Method 4: IP Override for Testing -->
        <div class="method info">
            <h2>Method 4: 🧪 Test with Different IP</h2>
            <p>Simulate different locations by testing with public IPs:</p>
            <div style="margin: 15px 0;">
                <button onclick="testLocation('8.8.8.8', 'Google DNS')" class="btn">🇺🇸 Test USA</button>
                <button onclick="testLocation('1.1.1.1', 'Cloudflare DNS')" class="btn">🇦🇺 Test Australia</button>
                <button onclick="testLocation('208.67.222.222', 'OpenDNS')" class="btn">🇺🇸 Test OpenDNS</button>
            </div>
            <div id="testLocation" style="margin-top: 15px;"></div>
        </div>

        <!-- Results Display -->
        <div class="method" id="locationResults" style="display: none;">
            <h2>📍 Location Results</h2>
            <div id="locationData"></div>
            <button onclick="saveLocationToSession()" class="btn">💾 Use This Location</button>
        </div>

        <!-- Action Buttons -->
        <div style="text-align: center; margin-top: 30px;">
            <a href="audit-trails.php" class="btn">📊 Back to Audit Trails</a>
            <a href="test_ip_location_fix.php" class="btn">🔧 IP Test Page</a>
        </div>
    </div>

    <script>
        let currentLocationData = null;

        // Method 1: Browser Geolocation
        function getBrowserLocation() {
            const resultDiv = document.getElementById('browserLocation');
            resultDiv.innerHTML = '🔄 Getting your location...';

            if (!navigator.geolocation) {
                resultDiv.innerHTML = '<div class="error">❌ Geolocation is not supported by this browser.</div>';
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    
                    // Use reverse geocoding to get city/country
                    reverseGeocode(lat, lon, function(locationData) {
                        currentLocationData = locationData;
                        resultDiv.innerHTML = `
                            <div class="success">
                                <h4>✅ Location Found!</h4>
                                <div class="location-display">
                                    📍 ${locationData.city}, ${locationData.country}
                                </div>
                                <p><strong>Coordinates:</strong> ${lat.toFixed(6)}, ${lon.toFixed(6)}</p>
                                <p><strong>Accuracy:</strong> ±${Math.round(position.coords.accuracy)}m</p>
                            </div>
                        `;
                        showLocationResults(locationData);
                    });
                },
                function(error) {
                    let errorMsg = 'Unknown error';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg = 'Location access denied by user';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg = 'Location information unavailable';
                            break;
                        case error.TIMEOUT:
                            errorMsg = 'Location request timed out';
                            break;
                    }
                    resultDiv.innerHTML = `<div class="error">❌ ${errorMsg}</div>`;
                }
            );
        }

        // Reverse geocoding using OpenStreetMap Nominatim
        function reverseGeocode(lat, lon, callback) {
            const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&addressdetails=1`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const address = data.address || {};
                    callback({
                        city: address.city || address.town || address.village || 'Unknown City',
                        country: address.country || 'Unknown Country',
                        region: address.state || address.region || 'Unknown Region',
                        country_code: address.country_code?.toUpperCase() || 'XX',
                        latitude: lat,
                        longitude: lon
                    });
                })
                .catch(error => {
                    console.error('Reverse geocoding failed:', error);
                    callback({
                        city: 'Unknown City',
                        country: 'Unknown Country',
                        region: 'Unknown Region',
                        country_code: 'XX',
                        latitude: lat,
                        longitude: lon
                    });
                });
        }

        // Method 3: Manual Location
        function setManualLocation(event) {
            event.preventDefault();
            const city = document.getElementById('manualCity').value.trim();
            const country = document.getElementById('manualCountry').value.trim();
            
            if (!city || !country) {
                document.getElementById('manualLocation').innerHTML = 
                    '<div class="error">❌ Please enter both city and country</div>';
                return;
            }

            currentLocationData = {
                city: city,
                country: country,
                region: 'Manual Entry',
                country_code: 'XX',
                latitude: null,
                longitude: null
            };

            document.getElementById('manualLocation').innerHTML = `
                <div class="success">
                    <h4>✅ Location Set!</h4>
                    <div class="location-display">📍 ${city}, ${country}</div>
                </div>
            `;

            showLocationResults(currentLocationData);
        }

        // Method 4: Test with different IPs
        function testLocation(ip, description) {
            const resultDiv = document.getElementById('testLocation');
            resultDiv.innerHTML = `🔄 Testing location for ${description} (${ip})...`;

            fetch(`test_ip_geolocation.php?ip=${ip}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentLocationData = data.location;
                        resultDiv.innerHTML = `
                            <div class="success">
                                <h4>✅ ${description} Location:</h4>
                                <div class="location-display">
                                    📍 ${data.location.city}, ${data.location.country}
                                </div>
                                <p><strong>IP:</strong> ${ip}</p>
                                <p><strong>ISP:</strong> ${data.location.isp || 'Unknown'}</p>
                            </div>
                        `;
                        showLocationResults(data.location);
                    } else {
                        resultDiv.innerHTML = `<div class="error">❌ Failed to get location for ${ip}</div>`;
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = `<div class="error">❌ Error testing ${description}</div>`;
                });
        }

        // Show location results
        function showLocationResults(locationData) {
            const resultsDiv = document.getElementById('locationResults');
            const dataDiv = document.getElementById('locationData');
            
            dataDiv.innerHTML = `
                <div class="location-display">
                    🌍 ${locationData.city}, ${locationData.country}
                </div>
                <table style="width: 100%; margin: 15px 0;">
                    <tr><td><strong>City:</strong></td><td>${locationData.city}</td></tr>
                    <tr><td><strong>Country:</strong></td><td>${locationData.country}</td></tr>
                    <tr><td><strong>Region:</strong></td><td>${locationData.region || 'N/A'}</td></tr>
                    <tr><td><strong>Country Code:</strong></td><td>${locationData.country_code || 'N/A'}</td></tr>
                    ${locationData.latitude ? `<tr><td><strong>Coordinates:</strong></td><td>${locationData.latitude}, ${locationData.longitude}</td></tr>` : ''}
                </table>
            `;
            
            resultsDiv.style.display = 'block';
        }

        // Save location to session for testing
        function saveLocationToSession() {
            if (!currentLocationData) return;

            fetch('save_test_location.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(currentLocationData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Location saved! Your audit entries will now show: ' + 
                          currentLocationData.city + ', ' + currentLocationData.country);
                    window.location.href = 'audit-trails.php';
                } else {
                    alert('❌ Failed to save location');
                }
            })
            .catch(error => {
                alert('❌ Error saving location');
            });
        }
    </script>
</body>
</html>
