// Diwali theme: fireworks behind the auth pages and a short burst on the first dashboard
// view after signing in. Only loaded while App\Support\DiwaliTheme::isActive().
(function () {
    'use strict';

    var WELCOME_KEY = 'dw-welcome-shown';
    var COLORS = ['#ffc857', '#ff5d8f', '#ff8c1a', '#fff1c2', '#2ec4b6', '#e8246f', '#ffd60a'];
    var MAX_PARTICLES = 700;

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function random(min, max) {
        return min + Math.random() * (max - min);
    }

    function pick(items) {
        return items[Math.floor(Math.random() * items.length)];
    }

    function fitCanvas(canvas) {
        var ratio = Math.min(window.devicePixelRatio || 1, 2);

        canvas.width = window.innerWidth * ratio;
        canvas.height = window.innerHeight * ratio;
        canvas.getContext('2d').setTransform(ratio, 0, 0, ratio, 0, 0);
    }

    function storage(action, value) {
        try {
            if (action === 'get') return window.sessionStorage.getItem(WELCOME_KEY);
            if (action === 'set') window.sessionStorage.setItem(WELCOME_KEY, value);
            if (action === 'clear') window.sessionStorage.removeItem(WELCOME_KEY);
        } catch (e) {
            // Storage can be blocked; the burst then simply replays on the next dashboard view.
        }

        return null;
    }

    function createFireworks(canvas, options) {
        var context = canvas.getContext('2d');
        var rockets = [];
        var particles = [];
        var running = false;
        var lastFrame = 0;
        var nextLaunch = 0;

        function explode(x, y) {
            var color = pick(COLORS);
            var accent = pick(COLORS);
            var ring = Math.random() < 0.35;
            var count = Math.round(random(60, 95));

            for (var i = 0; i < count && particles.length < MAX_PARTICLES; i++) {
                var angle = (i / count) * Math.PI * 2;
                var speed = ring ? 4.2 : random(0.8, 5.2);

                particles.push({
                    x: x,
                    y: y,
                    px: x,
                    py: y,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed,
                    life: 1,
                    decay: random(0.009, 0.018),
                    color: i % 4 === 0 ? accent : color,
                });
            }
        }

        function launch(targetX, targetY) {
            var height = window.innerHeight;
            var x = targetX === undefined ? random(0.08, 0.92) * window.innerWidth : targetX;
            var y = targetY === undefined ? random(0.12, 0.5) * height : targetY;

            rockets.push({
                x: x + random(-40, 40),
                y: height,
                targetY: y,
                targetX: x,
                speed: Math.max(9, (height - y) / 42),
            });
        }

        function frame(time) {
            if (!running) return;

            var step = Math.min((time - lastFrame) / 16.67 || 1, 3);
            lastFrame = time;

            if (time >= nextLaunch && options.autoLaunch()) {
                launch();
                nextLaunch = time + random(options.minGap, options.maxGap);
            }

            context.globalCompositeOperation = 'destination-out';
            context.fillStyle = 'rgba(0, 0, 0, ' + Math.min(0.2 * step, 0.6) + ')';
            context.fillRect(0, 0, window.innerWidth, window.innerHeight);
            context.globalCompositeOperation = options.blend;
            context.lineCap = 'round';

            rockets = rockets.filter(function (rocket) {
                var previousY = rocket.y;

                rocket.y -= rocket.speed * step;
                rocket.x += (rocket.targetX - rocket.x) * 0.02 * step;

                context.strokeStyle = '#ffe9b0';
                context.lineWidth = 2;
                context.beginPath();
                context.moveTo(rocket.x, previousY);
                context.lineTo(rocket.x, rocket.y);
                context.stroke();

                if (rocket.y <= rocket.targetY) {
                    explode(rocket.x, rocket.y);

                    return false;
                }

                return true;
            });

            particles = particles.filter(function (particle) {
                particle.px = particle.x;
                particle.py = particle.y;
                particle.vx *= Math.pow(0.975, step);
                particle.vy = particle.vy * Math.pow(0.975, step) + 0.045 * step;
                particle.x += particle.vx * step;
                particle.y += particle.vy * step;
                particle.life -= particle.decay * step;

                if (particle.life <= 0) return false;

                context.globalAlpha = particle.life;
                context.strokeStyle = particle.color;
                context.lineWidth = 2;
                context.beginPath();
                context.moveTo(particle.px, particle.py);
                context.lineTo(particle.x, particle.y);
                context.stroke();
                context.globalAlpha = 1;

                return true;
            });

            window.requestAnimationFrame(frame);
        }

        return {
            launch: launch,
            start: function () {
                running = true;
                lastFrame = window.performance.now();
                window.requestAnimationFrame(frame);
            },
            stop: function () {
                running = false;
            },
        };
    }

    function drawStars(canvas) {
        var context = canvas.getContext('2d');
        var width = window.innerWidth;
        var height = window.innerHeight;
        var total = Math.round((width * height) / 9000);

        context.clearRect(0, 0, width, height);

        for (var i = 0; i < total; i++) {
            context.globalAlpha = random(0.25, 0.95);
            context.fillStyle = Math.random() < 0.15 ? '#ffe3a3' : '#ffffff';
            context.beginPath();
            context.arc(random(0, width), random(0, height * 0.8), random(0.4, 1.4), 0, Math.PI * 2);
            context.fill();
        }

        context.globalAlpha = 1;
    }

    function startStage(stage) {
        var stars = stage.querySelector('.dw-stars');
        var sky = stage.querySelector('.dw-sky');

        // A new sign-in in this tab should get the dashboard burst again.
        storage('clear');

        function resize() {
            fitCanvas(stars);
            fitCanvas(sky);
            drawStars(stars);
        }

        resize();
        window.addEventListener('resize', resize);

        if (reducedMotion) return;

        var fireworks = createFireworks(sky, {
            blend: 'lighter',
            minGap: 700,
            maxGap: 1600,
            autoLaunch: function () {
                return true;
            },
        });

        fireworks.start();

        // Clicking the open sky sets off a firework there.
        document.addEventListener('pointerdown', function (event) {
            if (event.target.closest('.fi-simple-main, .fi-no, a, button, input, label')) return;

            fireworks.launch(event.clientX, event.clientY);
        });
    }

    function playWelcome() {
        if (reducedMotion || storage('get')) return;

        storage('set', '1');

        var canvas = document.createElement('canvas');
        var startedAt = window.performance.now();

        canvas.className = 'dw-burst';
        canvas.setAttribute('aria-hidden', 'true');
        document.body.appendChild(canvas);
        fitCanvas(canvas);

        var fireworks = createFireworks(canvas, {
            blend: 'source-over',
            minGap: 220,
            maxGap: 480,
            autoLaunch: function () {
                return window.performance.now() - startedAt < 2600;
            },
        });

        fireworks.start();

        window.setTimeout(function () {
            canvas.style.opacity = '0';
        }, 3800);

        window.setTimeout(function () {
            fireworks.stop();
            canvas.remove();
        }, 4800);
    }

    var stage = document.querySelector('[data-dw-stage]');

    if (stage) startStage(stage);
    if (document.querySelector('[data-dw-welcome]')) playWelcome();
})();
