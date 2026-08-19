</main><!-- end .page-content -->
</div><!-- end .main-wrap -->
</div><!-- end .app-shell -->

<!-- Toast notification -->
<div class="toast" id="toast"></div>

<!-- Modal overlay -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal-box" id="modalBox"></div>
</div>

<script src="/arams/assets/js/main.js"></script>
<script>
/* Global KPI count-up (all pages) — animates plain numeric .kpi-val */
(function(){
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    function animateVal(el){
        var raw = (el.textContent || '').trim();
        if (!/^[\d,]+(\.\d+)?$/.test(raw)) return;           // only plain numbers (skip time/%, text)
        var dec = (raw.split('.')[1] || '').length;
        var target = parseFloat(raw.replace(/,/g, ''));
        if (!isFinite(target) || target === 0) return;
        animate(el, target, dec);
    }
    function animateTarget(el){
        var target = parseFloat(el.getAttribute('data-target')) || 0;
        var dec = parseInt(el.getAttribute('data-dec') || '0', 10);
        if (target === 0) { el.textContent = (0).toFixed(dec); return; }
        animate(el, target, dec);
    }
    function animate(el, target, dec){
        var dur = 950, start = null;
        function fmt(v){ return v.toLocaleString('en-US', {minimumFractionDigits: dec, maximumFractionDigits: dec}); }
        function step(ts){ if(!start)start=ts; var p=Math.min((ts-start)/dur,1); var e=1-Math.pow(1-p,3);
            el.textContent=fmt(target*e); if(p<1) requestAnimationFrame(step); else el.textContent=fmt(target); }
        requestAnimationFrame(step);
    }
    function run(){
        document.querySelectorAll('.kpi-val').forEach(animateVal);
        document.querySelectorAll('.akpi-num[data-target]').forEach(animateTarget);
    }
    if (document.readyState !== 'loading') run(); else document.addEventListener('DOMContentLoaded', run);
})();
</script>
</body>
</html>