/**
 * HeatmapsRW Viewer
 * Renders heatmap visualizations
 */

var HeatmapsRWViewer = (function() {
    'use strict';
    
    var viewer = {
        container: null,
        canvas: null,
        ctx: null,
        data: null,
        config: {
            radius: 30,
            maxOpacity: 0.6,
            minOpacity: 0,
            blur: 0.85,
            gradient: {
                0.0: 'rgba(0, 0, 255, 0)',
                0.2: 'rgba(0, 0, 255, 1)',
                0.4: 'rgba(0, 255, 255, 1)',
                0.6: 'rgba(0, 255, 0, 1)',
                0.8: 'rgba(255, 255, 0, 1)',
                1.0: 'rgba(255, 0, 0, 1)'
            }
        },
        
        init: function(containerId, options) {
            this.container = document.getElementById(containerId);
            if (!this.container) {
                console.error('HeatmapsRW: Container not found');
                return;
            }
            
            // Merge options
            if (options) {
                Object.assign(this.config, options);
            }
            
            this.createCanvas();
            this.createGradient();
        },
        
        createCanvas: function() {
            this.canvas = document.createElement('canvas');
            this.canvas.className = 'heatmap-canvas';
            this.canvas.style.position = 'absolute';
            this.canvas.style.top = '0';
            this.canvas.style.left = '0';
            this.canvas.style.pointerEvents = 'none';
            this.canvas.style.zIndex = '9999';
            
            this.ctx = this.canvas.getContext('2d');
            this.container.appendChild(this.canvas);
            
            this.resize();
            window.addEventListener('resize', this.resize.bind(this));
        },
        
        resize: function() {
            this.canvas.width = this.container.scrollWidth;
            this.canvas.height = this.container.scrollHeight;
        },
        
        createGradient: function() {
            var gradientCanvas = document.createElement('canvas');
            var gradientCtx = gradientCanvas.getContext('2d');
            var gradient = gradientCtx.createLinearGradient(0, 0, 256, 0);
            
            gradientCanvas.width = 256;
            gradientCanvas.height = 1;
            
            for (var stop in this.config.gradient) {
                gradient.addColorStop(parseFloat(stop), this.config.gradient[stop]);
            }
            
            gradientCtx.fillStyle = gradient;
            gradientCtx.fillRect(0, 0, 256, 1);
            
            this.gradientData = gradientCtx.getImageData(0, 0, 256, 1).data;
        },
        
        loadData: function(url, period, date, callback) {
            var self = this;
            
            // Build API URL
            var apiUrl = 'index.php?module=API&method=HeatmapsRW.getHeatmapData';
            apiUrl += '&idSite=' + (window.piwik ? window.piwik.idSite : 1);
            apiUrl += '&period=' + period;
            apiUrl += '&date=' + date;
            apiUrl += '&url=' + encodeURIComponent(url);
            apiUrl += '&format=json';
            apiUrl += '&token_auth=' + (window.piwik ? window.piwik.token_auth : '');
            
            var xhr = new XMLHttpRequest();
            xhr.open('GET', apiUrl, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        self.data = data;
                        if (callback) callback(data);
                        self.render();
                    } catch (e) {
                        console.error('HeatmapsRW: Failed to parse data', e);
                    }
                }
            };
            xhr.onerror = function() {
                console.error('HeatmapsRW: Failed to load data');
            };
            xhr.send();
        },
        
        render: function() {
            if (!this.data || !this.canvas) return;
            
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            
            // Find the viewport that matches current size best
            var currentViewport = {
                width: window.innerWidth,
                height: window.innerHeight
            };
            
            var bestMatch = this.findBestViewportMatch(currentViewport);
            if (!bestMatch || !bestMatch.points || bestMatch.points.length === 0) {
                console.log('HeatmapsRW: No data points to render');
                return;
            }
            
            // Create shadow canvas for drawing points
            var shadowCanvas = document.createElement('canvas');
            shadowCanvas.width = this.canvas.width;
            shadowCanvas.height = this.canvas.height;
            var shadowCtx = shadowCanvas.getContext('2d');
            
            // Draw points
            var maxValue = this.getMaxValue(bestMatch.points);
            
            bestMatch.points.forEach(function(point) {
                if (point.x !== null && point.y !== null) {
                    var x = this.scaleX(point.x, bestMatch.viewport.width);
                    var y = this.scaleY(point.y, bestMatch.viewport.height);
                    var value = point.value / maxValue;
                    
                    this.drawPoint(shadowCtx, x, y, value);
                }
            }, this);
            
            // Apply colorization
            this.colorize(shadowCanvas);
        },
        
        findBestViewportMatch: function(current) {
            if (!this.data || this.data.length === 0) return null;
            
            var best = null;
            var minDiff = Infinity;
            
            this.data.forEach(function(viewport) {
                var diff = Math.abs(viewport.viewport.width - current.width) + 
                          Math.abs(viewport.viewport.height - current.height);
                if (diff < minDiff) {
                    minDiff = diff;
                    best = viewport;
                }
            });
            
            return best;
        },
        
        getMaxValue: function(points) {
            var max = 0;
            points.forEach(function(point) {
                if (point.value > max) max = point.value;
            });
            return max || 1;
        },
        
        scaleX: function(x, originalWidth) {
            return (x / originalWidth) * this.canvas.width;
        },
        
        scaleY: function(y, originalHeight) {
            return (y / originalHeight) * this.canvas.height;
        },
        
        drawPoint: function(ctx, x, y, value) {
            var radius = this.config.radius;
            
            // Create radial gradient
            var gradient = ctx.createRadialGradient(x, y, 0, x, y, radius);
            gradient.addColorStop(0, 'rgba(0,0,0,' + value + ')');
            gradient.addColorStop(1, 'rgba(0,0,0,0)');
            
            ctx.fillStyle = gradient;
            ctx.fillRect(x - radius, y - radius, radius * 2, radius * 2);
        },
        
        colorize: function(shadowCanvas) {
            var imageData = shadowCanvas.getContext('2d').getImageData(
                0, 0, shadowCanvas.width, shadowCanvas.height
            );
            
            var pixels = imageData.data;
            var opacity;
            var finalAlpha;
            
            for (var i = 0, len = pixels.length; i < len; i += 4) {
                opacity = pixels[i + 3];
                
                if (opacity > 0) {
                    // Normalize opacity
                    finalAlpha = opacity / 255;
                    finalAlpha = Math.max(this.config.minOpacity, 
                                         Math.min(this.config.maxOpacity, finalAlpha));
                    
                    // Get color from gradient
                    var gradientIndex = Math.min(255, Math.floor(opacity));
                    pixels[i] = this.gradientData[gradientIndex * 4];
                    pixels[i + 1] = this.gradientData[gradientIndex * 4 + 1];
                    pixels[i + 2] = this.gradientData[gradientIndex * 4 + 2];
                    pixels[i + 3] = finalAlpha * 255;
                }
            }
            
            this.ctx.putImageData(imageData, 0, 0);
        },
        
        showClickMap: function(url, period, date) {
            var self = this;
            var apiUrl = 'index.php?module=API&method=HeatmapsRW.getTopClickedElements';
            apiUrl += '&idSite=' + (window.piwik ? window.piwik.idSite : 1);
            apiUrl += '&period=' + period;
            apiUrl += '&date=' + date;
            apiUrl += '&url=' + encodeURIComponent(url);
            apiUrl += '&format=json';
            apiUrl += '&token_auth=' + (window.piwik ? window.piwik.token_auth : '');
            
            var xhr = new XMLHttpRequest();
            xhr.open('GET', apiUrl, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        self.renderClickMap(data);
                    } catch (e) {
                        console.error('HeatmapsRW: Failed to parse click data', e);
                    }
                }
            };
            xhr.send();
        },
        
        renderClickMap: function(elements) {
            // Remove existing overlays
            document.querySelectorAll('.heatmap-click-overlay').forEach(function(el) {
                el.remove();
            });
            
            elements.forEach(function(item) {
                if (!item.element_selector) return;
                
                try {
                    var element = document.querySelector(item.element_selector);
                    if (element) {
                        var overlay = document.createElement('div');
                        overlay.className = 'heatmap-click-overlay';
                        overlay.innerHTML = '<span class="click-count">' + item.clicks + '</span>';
                        
                        var rect = element.getBoundingClientRect();
                        overlay.style.position = 'absolute';
                        overlay.style.left = (rect.left + window.pageXOffset) + 'px';
                        overlay.style.top = (rect.top + window.pageYOffset) + 'px';
                        overlay.style.width = rect.width + 'px';
                        overlay.style.height = rect.height + 'px';
                        
                        document.body.appendChild(overlay);
                    }
                } catch (e) {
                    // Invalid selector, skip
                }
            });
        },
        
        clear: function() {
            if (this.ctx) {
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            }
            document.querySelectorAll('.heatmap-click-overlay').forEach(function(el) {
                el.remove();
            });
        }
    };
    
    return viewer;
})();