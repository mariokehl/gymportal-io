{{-- resources/views/widget-test.blade.php --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Widget Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f3f4f6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .test-section {
            background: white;
            margin-bottom: 30px;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-results {
            background: #f9fafb;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 14px;
        }
        .status-ok { color: #059669; }
        .status-error { color: #dc2626; }
        .widget-container-test {
            border: 2px dashed #d1d5db;
            padding: 20px;
            margin: 20px 0;
            min-height: 400px;
        }
        button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Widget Integration Test</h1>

        <!-- Asset-Status prüfen -->
        <div class="test-section">
            <h2>1. Asset-Status</h2>
            <button onclick="checkAssets()">Assets prüfen</button>
            <div id="asset-status" class="test-results">
                Klicke auf "Assets prüfen" um den Status zu überprüfen...
            </div>
        </div>

        <!-- Route-Tests -->
        <div class="test-section">
            <h2>2. Route-Tests</h2>
            <button onclick="testRoute('/embed/widget.js')">Test /embed/widget.js</button>
            <button onclick="testRoute('/embed/gymportal-widget.css')">Test /embed/gymportal-widget.css</button>
            <div id="route-status" class="test-results">
                Klicke auf einen Route-Test...
            </div>
        </div>

        <!-- Widget-Test -->
        <div class="test-section">
            <h2>3. Widget-Test</h2>

            <!-- Konfiguration -->
            <div style="background: #f9fafb; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <h3>Widget-Konfiguration</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; font-weight: 500; margin-bottom: 5px;">API-Key:</label>
                        <input
                            type="text"
                            id="widget-api-key"
                            placeholder="pk_live_abc123xyz789"
                            value="pk_live_abc123xyz789"
                            style="box-sizing: border-box; width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-family: monospace; font-size: 14px;"
                        >
                    </div>
                    <div>
                        <label style="display: block; font-weight: 500; margin-bottom: 5px;">Studio-ID:</label>
                        <input
                            type="number"
                            id="widget-studio-id"
                            placeholder="1"
                            value="1"
                            style="box-sizing: border-box; width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-family: monospace; font-size: 14px;"
                        >
                    </div>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" id="widget-debug-mode" checked>
                        <span>Debug-Modus aktivieren</span>
                    </label>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <button onclick="loadWidget()">Widget laden</button>
                <button onclick="clearWidget()">Widget vollständig entfernen</button>
                <button onclick="resetWidget()">Widget zurücksetzen</button>
                <button onclick="reloadWidgetScript()">Script neu laden</button>
            </div>

            <div id="gymportal-widget" class="widget-container-test">
                <!-- Widget wird hier geladen -->
            </div>

            <div id="widget-status" class="test-results">
                Widget noch nicht geladen...
            </div>
        </div>

        <!-- Debug-Informationen -->
        <div class="test-section">
            <h2>4. Debug-Informationen</h2>
            <button onclick="getDebugInfo()">Debug-Info laden</button>
            <div id="debug-info" class="test-results">
                Klicke auf "Debug-Info laden"...
            </div>
        </div>
    </div>

    <script>
        // Asset-Status prüfen
        async function checkAssets() {
            const statusDiv = document.getElementById('asset-status');
            statusDiv.innerHTML = 'Prüfe Assets...';

            try {
                const response = await fetch('/debug/widget-assets');
                const data = await response.json();

                statusDiv.innerHTML = `
                    <div class="${data.js_file_exists ? 'status-ok' : 'status-error'}">
                        ✓ JavaScript: ${data.js_file_exists ? 'Gefunden' : 'Nicht gefunden'} (${data.js_size} Bytes)
                    </div>
                    <div class="${data.css_file_exists ? 'status-ok' : 'status-error'}">
                        ✓ CSS: ${data.css_file_exists ? 'Gefunden' : 'Nicht gefunden'} (${data.css_size} Bytes)
                    </div>
                    <div>Laravel Version: ${data.laravel_version}</div>
                    <div>Public Path: ${data.public_path}</div>
                `;
            } catch (error) {
                statusDiv.innerHTML = `<div class="status-error">Fehler: ${error.message}</div>`;
            }
        }

        // Route testen
        async function testRoute(route) {
            const statusDiv = document.getElementById('route-status');
            statusDiv.innerHTML = `Teste Route: ${route}...`;

            try {
                const response = await fetch(route);
                const contentType = response.headers.get('content-type');
                const status = response.status;

                let content = '';
                if (response.ok) {
                    content = await response.text();
                    content = content.substring(0, 200) + (content.length > 200 ? '...' : '');
                }

                statusDiv.innerHTML = `
                    <div class="${response.ok ? 'status-ok' : 'status-error'}">
                        Route: ${route}<br>
                        Status: ${status}<br>
                        Content-Type: ${contentType}<br>
                        Content: ${content}
                    </div>
                `;
            } catch (error) {
                statusDiv.innerHTML = `<div class="status-error">Fehler bei ${route}: ${error.message}</div>`;
            }
        }

        // Widget laden mit benutzerdefinierten Werten
        async function loadWidget() {
            const statusDiv = document.getElementById('widget-status');
            const apiKey = document.getElementById('widget-api-key').value.trim();
            const studioId = document.getElementById('widget-studio-id').value.trim();
            const debugMode = document.getElementById('widget-debug-mode').checked;

            statusDiv.innerHTML = 'Lade Widget...';

            try {
                // Widget vollständig zurücksetzen
                await resetWidget();

                // Validierung
                if (!apiKey || !studioId) {
                    throw new Error('API-Key und Studio-ID sind erforderlich');
                }

                // Prüfen ob JavaScript geladen werden kann
                const jsResponse = await fetch('/embed/widget.js');
                if (!jsResponse.ok) {
                    throw new Error(`JavaScript konnte nicht geladen werden: ${jsResponse.status}`);
                }

                statusDiv.innerHTML = '<div class="status-ok">JavaScript verfügbar, lade Widget-Script...</div>';

                // Altes Script entfernen falls vorhanden
                const existingScript = document.querySelector('script[data-widget-script]');
                if (existingScript) {
                    existingScript.remove();
                }

                // JavaScript dynamisch laden
                const script = document.createElement('script');
                script.src = '/embed/widget.js?v=' + Date.now(); // Cache-Busting
                script.setAttribute('data-widget-script', 'true');

                script.onload = function() {
                    statusDiv.innerHTML = '<div class="status-ok">JavaScript geladen, initialisiere Widget...</div>';

                    try {
                        // Widget initialisieren mit benutzerdefinierten Werten
                        if (window.GymportalWidget) {
                            const widget = window.GymportalWidget.init({
                                containerId: 'gymportal-widget',
                                apiEndpoint: '{{ config("app.url") }}',
                                apiKey: apiKey,
                                studioId: studioId,
                                debugMode: debugMode,
                                theme: {
                                    primaryColor: '#3b82f6',
                                    secondaryColor: '#f8fafc',
                                    textColor: '#1f2937'
                                }
                            });

                            if (widget) {
                                statusDiv.innerHTML = `
                                    <div class="status-ok">
                                        Widget erfolgreich initialisiert!
                                    </div>
                                `;

                                // Widget-Instanz global verfügbar machen für weitere Tests
                                window.testWidget = widget;
                            } else {
                                statusDiv.innerHTML = '<div class="status-error">Widget-Initialisierung fehlgeschlagen</div>';
                            }
                        } else {
                            statusDiv.innerHTML = '<div class="status-error">GymportalWidget Objekt nicht gefunden</div>';
                        }
                    } catch (initError) {
                        statusDiv.innerHTML = `<div class="status-error">Widget-Init-Fehler: ${initError.message}</div>`;
                        console.error('Widget Init Error:', initError);
                    }
                };

                script.onerror = function() {
                    statusDiv.innerHTML = '<div class="status-error">JavaScript konnte nicht geladen werden</div>';
                };

                document.head.appendChild(script);

            } catch (error) {
                statusDiv.innerHTML = `<div class="status-error">Widget-Fehler: ${error.message}</div>`;
                console.error('Widget Load Error:', error);
            }
        }

        // Widget vollständig entfernen
        async function clearWidget() {
            const statusDiv = document.getElementById('widget-status');
            const widgetContainer = document.getElementById('gymportal-widget');

            try {
                // Widget-Container leeren
                if (widgetContainer) {
                    // Shadow DOM entfernen falls vorhanden
                    if (widgetContainer.shadowRoot) {
                        widgetContainer.shadowRoot.innerHTML = '';
                    }
                    // Normalen Content entfernen
                    widgetContainer.innerHTML = '';

                    // Alle Event-Listener entfernen durch Cloning
                    const newContainer = widgetContainer.cloneNode(false);
                    widgetContainer.parentNode.replaceChild(newContainer, widgetContainer);
                    newContainer.id = 'gymportal-widget';
                    newContainer.className = 'widget-container-test';
                }

                // Widget-Script entfernen
                const widgetScript = document.querySelector('script[data-widget-script]');
                if (widgetScript) {
                    widgetScript.remove();
                }

                // Globale Widget-Objekte entfernen
                if (window.GymportalWidget) {
                    delete window.GymportalWidget;
                }
                if (window.testWidget) {
                    delete window.testWidget;
                }

                statusDiv.innerHTML = '<div class="status-ok">Widget vollständig entfernt und aufgeräumt.</div>';

            } catch (error) {
                statusDiv.innerHTML = `<div class="status-error">Fehler beim Entfernen: ${error.message}</div>`;
                console.error('Clear Widget Error:', error);
            }
        }

        // Widget zurücksetzen (ohne Script zu entfernen)
        async function resetWidget() {
            const widgetContainer = document.getElementById('gymportal-widget');

            if (widgetContainer) {
                // Shadow DOM entfernen
                if (widgetContainer.shadowRoot) {
                    widgetContainer.shadowRoot.innerHTML = '';
                }
                widgetContainer.innerHTML = '';
            }

            // Globale Widget-Instanz zurücksetzen
            if (window.testWidget) {
                delete window.testWidget;
            }

            return new Promise(resolve => setTimeout(resolve, 100)); // Kurz warten
        }

        // Script neu laden (für Entwicklung)
        async function reloadWidgetScript() {
            const statusDiv = document.getElementById('widget-status');
            statusDiv.innerHTML = 'Script wird neu geladen...';

            try {
                // Altes Script entfernen
                const existingScript = document.querySelector('script[data-widget-script]');
                if (existingScript) {
                    existingScript.remove();
                }

                // Widget zurücksetzen
                await resetWidget();

                // Cache leeren durch force-reload des Scripts
                const timestamp = Date.now();
                const response = await fetch(`/embed/widget.js?v=${timestamp}`, {
                    cache: 'no-cache'
                });

                if (response.ok) {
                    statusDiv.innerHTML = '<div class="status-ok">Script neu geladen. Klicke auf "Widget laden" um es zu initialisieren.</div>';
                } else {
                    throw new Error(`Script reload failed: ${response.status}`);
                }

            } catch (error) {
                statusDiv.innerHTML = `<div class="status-error">Script-Reload-Fehler: ${error.message}</div>`;
                console.error('Script Reload Error:', error);
            }
        }

        // Debug-Informationen
        async function getDebugInfo() {
            const debugDiv = document.getElementById('debug-info');
            debugDiv.innerHTML = 'Lade Debug-Informationen...';

            try {
                const response = await fetch('/debug/widget-assets');
                const data = await response.json();

                debugDiv.innerHTML = `<pre>${JSON.stringify(data, null, 2)}</pre>`;
            } catch (error) {
                debugDiv.innerHTML = `<div class="status-error">Debug-Fehler: ${error.message}</div>`;
            }
        }

        // Auto-Load beim Seitenaufruf
        window.addEventListener('load', function() {
            checkAssets();
        });
    </script>
</body>
</html>
