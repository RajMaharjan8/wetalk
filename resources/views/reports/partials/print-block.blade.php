{{-- Replaces the printed output when the download is locked, so a free
     Cmd/Ctrl+P can't capture the report as a PDF. The on-screen preview is
     unaffected — only the print output is swapped for this notice. --}}
<div id="paywall-print" aria-hidden="true">
    <div>
        <h1>Payment required</h1>
        <p>This report can only be downloaded after payment. Return to the app and choose a payment method to continue.</p>
    </div>
</div>
<style>
    #paywall-print { display: none; }
    @media print {
        body > *:not(#paywall-print) { display: none !important; }
        #paywall-print {
            display: flex !important; align-items: center; justify-content: center;
            min-height: 90vh; text-align: center; font-family: system-ui, sans-serif;
        }
        #paywall-print h1 { font-size: 20px; margin: 0 0 10px; }
        #paywall-print p { color: #444; font-size: 13px; max-width: 360px; margin: 0 auto; }
    }
</style>
<script>
    // Best-effort nudge; the print stylesheet above is the real block.
    window.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'p') {
            e.preventDefault();
            alert('This report requires payment before it can be downloaded.');
        }
    });
</script>
