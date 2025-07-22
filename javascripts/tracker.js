/**
 * HeatmapsRW Tracker
 * Tracks user interactions for heatmap generation
 */

(function () {
    'use strict';

    // Check if Matomo tracker exists
    if (typeof window._paq === 'undefined') {
        return;
    }

    var HeatmapsRWTracker = {
        events: [],
        config: {
            maxEvents: 50,
            flushInterval: 5000,
            trackClicks: true,
            trackMouseMove: false,
            trackScroll: true,
            throttleDelay: 200
        },
        flushTimer: null,
        
        init: function() {
            this.bindEvents();
            this.startFlushTimer();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Track clicks
            if (this.config.trackClicks) {
                document.addEventListener('click', function(e) {
                    self.trackClick(e);
                }, true);
            }
            
            // Track mouse movements (throttled)
            if (this.config.trackMouseMove) {
                var mouseTimer;
                document.addEventListener('mousemove', function(e) {
                    clearTimeout(mouseTimer);
                    mouseTimer = setTimeout(function() {
                        self.trackMouseMove(e);
                    }, self.config.throttleDelay);
                });
            }
            
            // Track scroll (throttled)
            if (this.config.trackScroll) {
                var scrollTimer;
                window.addEventListener('scroll', function(e) {
                    clearTimeout(scrollTimer);
                    scrollTimer = setTimeout(function() {
                        self.trackScroll(e);
                    }, self.config.throttleDelay);
                });
            }
            
            // Flush events before page unload
            window.addEventListener('beforeunload', function() {
                self.flush(true);
            });
        },
        
        trackClick: function(event) {
            var data = {
                type: 'click',
                x: event.pageX,
                y: event.pageY,
                viewport_width: window.innerWidth,
                viewport_height: window.innerHeight,
                page_width: document.documentElement.scrollWidth,
                page_height: document.documentElement.scrollHeight,
                url: window.location.href,
                timestamp: Date.now()
            };
            
            // Get element info
            var element = event.target;
            if (element) {
                data.selector = this.getElementSelector(element);
                data.text = this.getElementText(element);
            }
            
            this.addEvent(data);
        },
        
        trackMouseMove: function(event) {
            var data = {
                type: 'mousemove',
                x: event.pageX,
                y: event.pageY,
                viewport_width: window.innerWidth,
                viewport_height: window.innerHeight,
                page_width: document.documentElement.scrollWidth,
                page_height: document.documentElement.scrollHeight,
                url: window.location.href,
                timestamp: Date.now()
            };
            
            this.addEvent(data);
        },
        
        trackScroll: function() {
            var data = {
                type: 'scroll',
                x: window.pageXOffset,
                y: window.pageYOffset,
                viewport_width: window.innerWidth,
                viewport_height: window.innerHeight,
                page_width: document.documentElement.scrollWidth,
                page_height: document.documentElement.scrollHeight,
                url: window.location.href,
                timestamp: Date.now()
            };
            
            this.addEvent(data);
        },
        
        getElementSelector: function(element) {
            var selector = '';
            var path = [];
            
            while (element && element.nodeType === Node.ELEMENT_NODE) {
                var part = element.tagName.toLowerCase();
                
                if (element.id) {
                    part += '#' + element.id;
                    path.unshift(part);
                    break;
                } else {
                    var classes = element.className;
                    if (classes && typeof classes === 'string') {
                        classes = classes.trim();
                        if (classes) {
                            part += '.' + classes.split(/\s+/).join('.');
                        }
                    }
                    
                    var sibling = element;
                    var nth = 1;
                    
                    while ((sibling = sibling.previousElementSibling) != null) {
                        if (sibling.tagName === element.tagName) {
                            nth++;
                        }
                    }
                    
                    if (nth > 1) {
                        part += ':nth-of-type(' + nth + ')';
                    }
                    
                    path.unshift(part);
                    element = element.parentElement;
                }
            }
            
            return path.join(' > ');
        },
        
        getElementText: function(element) {
            var text = element.textContent || element.innerText || '';
            text = text.trim().substring(0, 100);
            return text;
        },
        
        addEvent: function(data) {
            this.events.push(data);
            
            if (this.events.length >= this.config.maxEvents) {
                this.flush();
            }
        },
        
        startFlushTimer: function() {
            var self = this;
            this.flushTimer = setInterval(function() {
                if (self.events.length > 0) {
                    self.flush();
                }
            }, this.config.flushInterval);
        },
        
        flush: function(isUnload) {
            if (this.events.length === 0 || typeof window._paq === 'undefined') {
                return;
            }
            
            var eventsToSend = this.events.splice(0, this.config.maxEvents);
            var jsonData = JSON.stringify(eventsToSend);
            
            // Send data with tracking request
            if (isUnload) {
                // Use sendBeacon for unload events if available
                if (navigator.sendBeacon && window.Matomo && window.Matomo.getAsyncTracker) {
                    var tracker = window.Matomo.getAsyncTracker();
                    var url = tracker.getTrackerUrl();
                    var params = 'idsite=' + tracker.getSiteId() + 
                               '&rec=1' +
                               '&heatmap_data=' + encodeURIComponent(jsonData);
                    navigator.sendBeacon(url, params);
                }
            } else {
                // Regular tracking
                _paq.push(['setCustomRequestProcessing', function(request) {
                    return request + '&heatmap_data=' + encodeURIComponent(jsonData);
                }]);
                _paq.push(['trackEvent', 'HeatmapRW', 'data', 'batch']);
                _paq.push(['setCustomRequestProcessing', null]);
            }
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            HeatmapsRWTracker.init();
        });
    } else {
        HeatmapsRWTracker.init();
    }
    
    // Export for external use
    window.HeatmapsRWTracker = HeatmapsRWTracker;
})();