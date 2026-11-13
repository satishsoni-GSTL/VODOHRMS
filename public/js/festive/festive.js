// Shared celebration effects: fireworks, confetti, balloons, snow, colour powder (Holi) and
// tricolour confetti. Runs continuously behind the sign-in card ([data-fx-stage]) and as a
// short burst on the first dashboard view after signing in ([data-fx-welcome]).
// Plain JS, no build step. Respects prefers-reduced-motion.
(function () {
    'use strict';

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var MAX = 600;

    function rnd(min, max) { return min + Math.random() * (max - min); }
    function pick(list) { return list[Math.floor(Math.random() * list.length)]; }

    function fit(canvas) {
        var ratio = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = window.innerWidth * ratio;
        canvas.height = window.innerHeight * ratio;
        canvas.getContext('2d').setTransform(ratio, 0, 0, ratio, 0, 0);
    }

    function storage(key, action) {
        try {
            if (action === 'get') return window.sessionStorage.getItem(key);
            if (action === 'set') window.sessionStorage.setItem(key, '1');
            if (action === 'clear') window.sessionStorage.removeItem(key);
        } catch (e) { /* storage blocked: the burst just replays */ }
        return null;
    }

    // ---- Effects. Each returns { spawn(burst), step(ctx, dt, w, h) -> keepAlive } -----------

    function fireworks(colors) {
        var rockets = [], sparks = [];
        function explode(x, y) {
            var c1 = pick(colors), c2 = pick(colors), n = Math.round(rnd(55, 90)), ring = Math.random() < 0.35;
            for (var i = 0; i < n && sparks.length < MAX; i++) {
                var a = (i / n) * Math.PI * 2, s = ring ? 4 : rnd(0.8, 5);
                sparks.push({ x: x, y: y, px: x, py: y, vx: Math.cos(a) * s, vy: Math.sin(a) * s, life: 1, decay: rnd(0.009, 0.018), c: i % 4 ? c1 : c2 });
            }
        }
        return {
            trails: true,
            spawn: function (w, h, x, y) {
                var tx = x === undefined ? rnd(0.1, 0.9) * w : x, ty = y === undefined ? rnd(0.12, 0.5) * h : y;
                rockets.push({ x: tx + rnd(-40, 40), y: h, tx: tx, ty: ty, v: Math.max(9, (h - ty) / 42) });
            },
            step: function (ctx, dt) {
                ctx.lineCap = 'round';
                rockets = rockets.filter(function (r) {
                    var py = r.y; r.y -= r.v * dt; r.x += (r.tx - r.x) * 0.02 * dt;
                    ctx.strokeStyle = '#fff3c4'; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(r.x, py); ctx.lineTo(r.x, r.y); ctx.stroke();
                    if (r.y <= r.ty) { explode(r.x, r.y); return false; }
                    return true;
                });
                sparks = sparks.filter(function (p) {
                    p.px = p.x; p.py = p.y; p.vx *= Math.pow(0.975, dt); p.vy = p.vy * Math.pow(0.975, dt) + 0.045 * dt;
                    p.x += p.vx * dt; p.y += p.vy * dt; p.life -= p.decay * dt;
                    if (p.life <= 0) return false;
                    ctx.globalAlpha = p.life; ctx.strokeStyle = p.c; ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.moveTo(p.px, p.py); ctx.lineTo(p.x, p.y); ctx.stroke(); ctx.globalAlpha = 1;
                    return true;
                });
                return rockets.length + sparks.length > 0;
            },
            gap: [650, 1500],
        };
    }

    function confetti(colors, fromTop) {
        var bits = [];
        return {
            spawn: function (w, h, x, y, burst) {
                var n = burst ? 140 : 6;
                for (var i = 0; i < n && bits.length < MAX; i++) {
                    bits.push({
                        x: x === undefined ? rnd(0, w) : x + rnd(-30, 30), y: burst && !fromTop ? (y === undefined ? h * 0.35 : y) : rnd(-40, -10),
                        vx: burst ? rnd(-6, 6) : rnd(-0.6, 0.6), vy: burst ? rnd(-9, -2) : rnd(1, 2.4),
                        w: rnd(6, 11), h: rnd(3, 6), r: rnd(0, Math.PI), vr: rnd(-0.15, 0.15), c: pick(colors), sway: rnd(0, 6.28),
                    });
                }
            },
            step: function (ctx, dt, w, h) {
                bits = bits.filter(function (b) {
                    b.vy = Math.min(b.vy + 0.12 * dt, 3.2); b.vx *= Math.pow(0.985, dt); b.sway += 0.05 * dt;
                    b.x += (b.vx + Math.sin(b.sway) * 0.6) * dt; b.y += b.vy * dt; b.r += b.vr * dt;
                    if (b.y > h + 20) return false;
                    ctx.save(); ctx.translate(b.x, b.y); ctx.rotate(b.r); ctx.fillStyle = b.c;
                    ctx.fillRect(-b.w / 2, -b.h / 2, b.w, b.h * Math.abs(Math.cos(b.sway))); ctx.restore();
                    return true;
                });
                return bits.length > 0;
            },
            gap: [90, 220],
        };
    }

    function balloons(colors) {
        var list = [];
        function draw(ctx, b) {
            ctx.strokeStyle = 'rgba(255,255,255,.55)'; ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(b.x, b.y + b.r * 1.2);
            ctx.quadraticCurveTo(b.x + Math.sin(b.t) * 10, b.y + b.r * 2.2, b.x, b.y + b.r * 3.4); ctx.stroke();
            var g = ctx.createRadialGradient(b.x - b.r * 0.35, b.y - b.r * 0.4, b.r * 0.1, b.x, b.y, b.r * 1.2);
            g.addColorStop(0, 'rgba(255,255,255,.85)'); g.addColorStop(0.25, b.c); g.addColorStop(1, b.c);
            ctx.fillStyle = g; ctx.beginPath(); ctx.ellipse(b.x, b.y, b.r, b.r * 1.2, 0, 0, Math.PI * 2); ctx.fill();
            ctx.beginPath(); ctx.moveTo(b.x - 4, b.y + b.r * 1.25); ctx.lineTo(b.x + 4, b.y + b.r * 1.25); ctx.lineTo(b.x, b.y + b.r * 1.15); ctx.fill();
        }
        return {
            spawn: function (w, h, x, y, burst) {
                var n = burst ? 18 : 1;
                for (var i = 0; i < n; i++) {
                    list.push({ x: x === undefined ? rnd(0.04, 0.96) * w : x, y: h + rnd(20, burst ? 260 : 60), r: rnd(16, 28), v: rnd(1.1, 2.2), t: rnd(0, 6), c: pick(colors) });
                }
            },
            step: function (ctx, dt) {
                list = list.filter(function (b) {
                    b.y -= b.v * dt; b.t += 0.03 * dt; b.x += Math.sin(b.t) * 0.5 * dt;
                    if (b.y < -b.r * 4) return false;
                    draw(ctx, b);
                    return true;
                });
                return list.length > 0;
            },
            gap: [700, 1300],
            extra: function () { return confetti(colors, true); },
        };
    }

    function snow() {
        var flakes = [];
        return {
            spawn: function (w, h, x, y, burst) {
                var n = burst ? 120 : 4;
                for (var i = 0; i < n && flakes.length < MAX; i++) {
                    flakes.push({ x: rnd(0, w), y: burst ? rnd(-h * 0.6, 0) : -10, r: rnd(1.2, 3.6), v: rnd(0.5, 1.6), t: rnd(0, 6), a: rnd(0.5, 1) });
                }
            },
            step: function (ctx, dt, w, h) {
                ctx.fillStyle = '#ffffff';
                flakes = flakes.filter(function (f) {
                    f.y += f.v * dt; f.t += 0.02 * dt; f.x += Math.sin(f.t) * 0.4 * dt;
                    if (f.y > h + 10) return false;
                    ctx.globalAlpha = f.a; ctx.beginPath(); ctx.arc(f.x, f.y, f.r, 0, Math.PI * 2); ctx.fill(); ctx.globalAlpha = 1;
                    return true;
                });
                return flakes.length > 0;
            },
            gap: [60, 140],
        };
    }

    function colorPowder(colors) {
        var clouds = [];
        var bits = confetti(colors, false);
        return {
            spawn: function (w, h, x, y, burst) {
                var cx = x === undefined ? rnd(0.1, 0.9) * w : x, cy = y === undefined ? rnd(0.2, 0.8) * h : y;
                var n = burst ? 6 : 1;
                for (var i = 0; i < n; i++) {
                    clouds.push({ x: cx + rnd(-90, 90) * (burst ? 2 : 0.3), y: cy + rnd(-60, 60) * (burst ? 2 : 0.3), r: 4, max: rnd(80, 170), life: 1, c: pick(colors) });
                }
                bits.spawn(w, h, cx, cy, true);
            },
            step: function (ctx, dt, w, h) {
                clouds = clouds.filter(function (c) {
                    c.r += (c.max - c.r) * 0.04 * dt; c.life -= 0.006 * dt;
                    if (c.life <= 0) return false;
                    var g = ctx.createRadialGradient(c.x, c.y, 0, c.x, c.y, c.r);
                    g.addColorStop(0, c.c); g.addColorStop(1, 'rgba(0,0,0,0)');
                    ctx.globalAlpha = c.life * 0.55; ctx.fillStyle = g; ctx.beginPath(); ctx.arc(c.x, c.y, c.r, 0, Math.PI * 2); ctx.fill(); ctx.globalAlpha = 1;
                    return true;
                });
                var more = bits.step(ctx, dt, w, h);
                return clouds.length > 0 || more;
            },
            gap: [900, 1800],
        };
    }

    function effectFor(name, colors) {
        switch (name) {
            case 'fireworks': return fireworks(colors);
            case 'balloons': return balloons(colors);
            case 'snow': return snow();
            case 'colors': return colorPowder(colors);
            case 'tricolour': return confetti(['#ff9933', '#ffffff', '#138808', '#ff9933', '#138808'], true);
            default: return confetti(colors, true);
        }
    }

    // ---- Runner ----------------------------------------------------------------------------

    function run(canvas, name, colors, opts) {
        var ctx = canvas.getContext('2d');
        var effects = [effectFor(name, colors)];
        if (effects[0].extra) effects.push(effects[0].extra());
        var last = performance.now(), next = 0, running = true, started = last;

        function frame(now) {
            if (!running) return;
            var dt = Math.min((now - last) / 16.67 || 1, 3), w = window.innerWidth, h = window.innerHeight;
            last = now;

            if (effects[0].trails) {
                ctx.globalCompositeOperation = 'destination-out';
                ctx.fillStyle = 'rgba(0,0,0,' + Math.min(0.2 * dt, 0.6) + ')';
                ctx.fillRect(0, 0, w, h);
                ctx.globalCompositeOperation = opts.blend || 'source-over';
            } else {
                ctx.clearRect(0, 0, w, h);
            }

            if (now >= next && opts.keepSpawning(now - started)) {
                effects.forEach(function (e) { e.spawn(w, h); });
                var gap = effects[0].gap || [300, 800];
                next = now + rnd(gap[0], gap[1]) * (opts.fast ? 0.35 : 1);
            }

            var alive = false;
            effects.forEach(function (e) { alive = e.step(ctx, dt, w, h) || alive; });

            if (!alive && !opts.keepSpawning(now - started) && opts.onDone) { running = false; opts.onDone(); return; }
            requestAnimationFrame(frame);
        }

        requestAnimationFrame(frame);

        return {
            burstAt: function (x, y) { effects.forEach(function (e) { e.spawn(window.innerWidth, window.innerHeight, x, y, true); }); },
            burst: function () { effects.forEach(function (e) { e.spawn(window.innerWidth, window.innerHeight, undefined, undefined, true); }); },
        };
    }

    function colorsOf(el) {
        try { return JSON.parse(el.getAttribute('data-fx-colors')) || ['#ffffff']; } catch (e) { return ['#ffffff']; }
    }

    // Sign-in backdrop: continuous, and clicking the open background sets off a burst there.
    var stage = document.querySelector('[data-fx-stage]');
    if (stage) {
        var stageCanvas = stage.querySelector('.fx-canvas');
        fit(stageCanvas);
        window.addEventListener('resize', function () { fit(stageCanvas); });

        // A fresh sign-in should get the dashboard burst again.
        try {
            Object.keys(window.sessionStorage).forEach(function (k) { if (k.indexOf('fx-welcome-') === 0) storage(k, 'clear'); });
        } catch (e) { /* storage blocked */ }

        if (!reducedMotion) {
            var stageFx = run(stageCanvas, stage.getAttribute('data-fx-effect'), colorsOf(stage), {
                blend: 'lighter',
                keepSpawning: function () { return true; },
            });
            document.addEventListener('pointerdown', function (event) {
                if (event.target.closest('.fi-simple-main, .fi-no, a, button, input, label')) return;
                stageFx.burstAt(event.clientX, event.clientY);
            });
        }
    }

    // Dashboard: one short burst per sign-in (per theme).
    var welcome = document.querySelector('[data-fx-welcome]');
    if (welcome && !reducedMotion) {
        var key = 'fx-welcome-' + welcome.getAttribute('data-fx-welcome');
        if (!storage(key, 'get')) {
            storage(key, 'set');
            var canvas = document.createElement('canvas');
            canvas.className = 'fx-burst';
            canvas.setAttribute('aria-hidden', 'true');
            document.body.appendChild(canvas);
            fit(canvas);

            var fx = run(canvas, welcome.getAttribute('data-fx-effect'), colorsOf(welcome), {
                fast: true,
                keepSpawning: function (elapsed) { return elapsed < 2600; },
                onDone: function () { canvas.remove(); },
            });
            fx.burst();

            setTimeout(function () { canvas.style.opacity = '0'; }, 4200);
            setTimeout(function () { canvas.remove(); }, 5400);
        }
    }
})();
