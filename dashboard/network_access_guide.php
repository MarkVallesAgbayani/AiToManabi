<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<h1 style='color: red;'>❌ Access Denied - Admin Only</h1>";
    exit();
}

// Get server information
$serverIP = $_SERVER['SERVER_ADDR'] ?? 'Unknown';
$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
$serverPort = $_SERVER['SERVER_PORT'] ?? '80';

// Try to get the actual network IP
function getNetworkIP() {
    // Try different methods to get network IP
    $methods = [
        'ipconfig /all | findstr IPv4',  // Windows
        'hostname -I',                   // Linux
        'ifconfig | grep inet'          // Unix/Mac
    ];
    
    foreach ($methods as $method) {
        $output = shell_exec($method);
        if ($output) {
            // Parse output to find network IP
            if (preg_match('/192\.168\.\d+\.\d+/', $output, $matches)) {
                return $matches[0];
            }
            if (preg_match('/10\.\d+\.\d+\.\d+/', $output, $matches)) {
                return $matches[0];
            }
        }
    }
    
    return null;
}

$networkIP = getNetworkIP();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌐 Network Access Guide - Japanese Learning Platform</title>
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
        .url-display { font-family: monospace; font-size: 16px; padding: 15px; background: #f8f9fa; border-radius: 8px; margin: 10px 0; border: 2px solid #007bff; }
        .ip-display { font-family: monospace; font-size: 14px; padding: 8px; background: #e9ecef; border-radius: 4px; margin: 5px 0; }
        .copy-btn { background: #28a745; font-size: 12px; padding: 5px 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌐 Network Access Guide</h1>
        <p>To get your real location (like Quezon City), you need to access the system from your network IP instead of localhost.</p>

        <!-- Current Status -->
        <div class="method warning">
            <h2>📍 Current Status</h2>
            <p><strong>You're currently accessing via:</strong></p>
            <div class="ip-display">
                <strong>URL:</strong> <?php echo $serverName; ?><br>
                <strong>Your IP:</strong> ::1 (localhost)<br>
                <strong>Result:</strong> Shows "Local Machine" instead of real location
            </div>
        </div>

        <!-- Method 1: Network IP Access -->
        <div class="method success">
            <h2>🏠 Method 1: Access via Network IP</h2>
            <?php if ($networkIP): ?>
                <p>✅ <strong>Your network IP detected:</strong> <?php echo $networkIP; ?></p>
                <p>Try accessing the system using this URL:</p>
                <div class="url-display">
                    <strong>http://<?php echo $networkIP; ?><?php echo $serverPort != '80' ? ':' . $serverPort : ''; ?>/AIToManabi_Updated/dashboard/audit-trails.php</strong>
                    <button onclick="copyToClipboard('http://<?php echo $networkIP; ?><?php echo $serverPort != '80' ? ':' . $serverPort : ''; ?>/AIToManabi_Updated/')" class="btn copy-btn">📋 Copy</button>
                </div>
                <p><strong>Steps:</strong></p>
                <ol>
                    <li>Copy the URL above</li>
                    <li>Open a new browser tab</li>
                    <li>Paste and visit the URL</li>
                    <li>Log in again</li>
                    <li>Check audit trails - should show your real location!</li>
                </ol>
            <?php else: ?>
                <p>⚠️ Could not automatically detect your network IP.</p>
                <p><strong>Manual method:</strong></p>
                <ol>
                    <li>Open Command Prompt (Windows) or Terminal (Mac/Linux)</li>
                    <li>Type: <code>ipconfig</code> (Windows) or <code>ifconfig</code> (Mac/Linux)</li>
                    <li>Find your network IP (usually 192.168.x.x or 10.x.x.x)</li>
                    <li>Replace localhost with that IP in your browser</li>
                </ol>
            <?php endif; ?>
        </div>

        <!-- Method 2: Mobile Hotspot -->
        <div class="method info">
            <h2>📱 Method 2: Mobile Hotspot</h2>
            <p>For even more accurate location detection:</p>
            <ol>
                <li><strong>Enable mobile hotspot</strong> on your phone</li>
                <li><strong>Connect your computer</strong> to the mobile hotspot</li>
                <li><strong>Access the system</strong> - now you'll have a public IP</li>
                <li><strong>Check location</strong> - should show your actual city!</li>
            </ol>
            <p><strong>Why this works:</strong> Mobile networks give you a public IP that can be geolocated to your actual city.</p>
        </div>

        <!-- Method 3: Manual Override -->
        <div class="method success">
            <h2>✏️ Method 3: Manual Location Override (Recommended for Testing)</h2>
            <p>Set your location manually while using localhost:</p>
            <a href="get_real_location.php" class="btn">🌍 Set My Location to Quezon City</a>
            <p><strong>This will:</strong></p>
            <ul>
                <li>Override localhost location detection</li>
                <li>Show "Quezon City, Philippines" in audit trails</li>
                <li>Work immediately without changing networks</li>
                <li>Perfect for testing and development</li>
            </ul>
        </div>

        <!-- Method 4: Live Server -->
        <div class="method warning">
            <h2>🌍 Method 4: Deploy to Live Server</h2>
            <p>For production use with real geolocation:</p>
            <ul>
                <li><strong>Deploy to web hosting</strong> (shared hosting, VPS, cloud)</li>
                <li><strong>Access via domain</strong> (yoursite.com instead of localhost)</li>
                <li><strong>Users get real IPs</strong> and accurate location detection</li>
                <li><strong>Full geolocation</strong> with city, country, ISP details</li>
            </ul>
        </div>

        <!-- Current Network Info -->
        <div class="method info">
            <h2>🔍 Your Current Network Information</h2>
            <table style="width: 100%; margin: 15px 0;">
                <tr><td><strong>Server Name:</strong></td><td><?php echo $serverName; ?></td></tr>
                <tr><td><strong>Server IP:</strong></td><td><?php echo $serverIP; ?></td></tr>
                <tr><td><strong>Server Port:</strong></td><td><?php echo $serverPort; ?></td></tr>
                <?php if ($networkIP): ?>
                <tr><td><strong>Network IP:</strong></td><td><?php echo $networkIP; ?></td></tr>
                <?php endif; ?>
                <tr><td><strong>Your Current IP:</strong></td><td>::1 (localhost)</td></tr>
                <tr><td><strong>Location Result:</strong></td><td>Local Machine</td></tr>
            </table>
        </div>

        <!-- Quick Actions -->
        <div style="text-align: center; margin-top: 30px;">
            <a href="get_real_location.php" class="btn">🎯 Set Location Now</a>
            <a href="audit-trails.php" class="btn">📊 Back to Audit Trails</a>
            <a href="test_ip_location_fix.php" class="btn">🔧 Test IP Functions</a>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('✅ URL copied to clipboard!');
            }, function(err) {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('✅ URL copied to clipboard!');
            });
        }
    </script>
</body>
</html>
