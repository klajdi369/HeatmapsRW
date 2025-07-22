/**
 * HeatmapsRW Tracker - Auto-included in matomo.js
 */

(function () {
    'use strict';

    function init() {
        console.log('HeatmapsRW: Auto-tracker initialized');
        
        var events = [];
        var config = {
            maxEvents: 10,
            flushInterval: 5000
        };
        
        function trackClick(event) {
            console.log('HeatmapsRW: Auto-click tracked');
            
            var data = {
                type: 'click',
                x: event.pageX,
                y: event.pageY,
                viewport_width: window.innerWidth,
                viewport_height: window.innerHeight,
                page_width: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth || 0),
                page_height: Math.max(document.documentElement.scrollHeight, document.body.scrollHeight || 0),
                url: window.location.href,
                timestamp: Date.now()
            };
            
            if (event.target) {
                data.selector = event.target.tagName ? event.target.tagName.toLowerCase() : 'unknown';
                data.text = (event.target.textContent || event.target.innerText || '').substring(0, 100);
            }
            
            events.push(data);
            
            if (events.length >= config.maxEvents) {
                flush();
            }
        }
        
        function flush() {
            if (events.length === 0) return;
            
            var dataToSend = events.splice(0);
            console.log('HeatmapsRW: Flushing', dataToSend.length, 'events');
            
            // Send via AJAX
            var xhr = new XMLHttpRequest();
            var url = (function() {
                // Try to get Matomo URL from existing tracker
                try {
                    if (typeof Matomo !== 'undefined') {
                        var trackers = Matomo.getAsyncTrackers();
                        if (trackers && trackers.length > 0) {
                            var trackerUrl = trackers[0].getTrackerUrl();
                            var siteId = trackers[0].getSiteId();
                            var baseUrl = trackerUrl.replace(/matomo\.php.*$/, '');
                            return baseUrl + 'index.php?module=HeatmapsRW&action=track&idSite=' + siteId;
                        }
                    }
                } catch (e) {
                    console.log('HeatmapsRW: Could not get tracker URL:', e);
                }
                
                // Fallback - construct from current location
                var baseUrl = window.location.protocol + '//' + window.location.host;
                if (window.location.pathname.indexOf('/') === 0 && window.location.pathname !== '/') {
                    baseUrl += window.location.pathname.split('/').slice(0, -1).join('/');
                }
                return baseUrl + '/index.php?module=HeatmapsRW&action=track&idSite=1';
            })();
            
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    console.log('HeatmapsRW: Server response:', xhr.status, xhr.responseText);
                }
            };
            xhr.send(JSON.stringify(dataToSend));
            console.log('HeatmapsRW: Sent to:', url);
        }
        
        // Attach click listener
        document.addEventListener('click', trackClick, true);
        
        // Flush periodically
        setInterval(function() {
            if (events.length > 0) {
                flush();
            }
        }, config.flushInterval);
        
        // Flush on page unload
        window.addEventListener('beforeunload', flush);
        
        console.log('HeatmapsRW: Auto-tracker ready');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();