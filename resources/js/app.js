import QRCode from 'qrcode';
import { Html5Qrcode } from 'html5-qrcode';

/**
 * Alpine component that renders a QR code for the given URL into a <canvas x-ref="canvas">.
 * Used on the owner reservation list so the attendant can scan a reservation at the desk.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('qrCode', (url) => ({
        url,
        render() {
            const canvas = this.$refs.canvas;

            if (!canvas || !this.url) {
                return;
            }

            QRCode.toCanvas(canvas, this.url, { width: 220, margin: 1 }, (error) => {
                if (error) {
                    console.error('QR render failed', error);
                }
            });
        },
    }));

    /**
     * Alpine component that streams the device (rear) camera and reads a
     * reservation QR. On a successful decode it navigates to the encoded URL.
     * Requires a secure context (https or localhost) — the camera API is
     * unavailable over plain http.
     */
    window.Alpine.data('qrScanner', () => ({
        scanner: null,
        active: false,
        error: '',

        async start() {
            if (this.active) {
                return;
            }

            this.error = '';

            if (!navigator.mediaDevices?.getUserMedia) {
                this.error = 'Kamera nije dostupna. Potreban je HTTPS (ili localhost).';

                return;
            }

            this.active = true;
            await this.$nextTick();

            this.scanner = new Html5Qrcode('qr-reader');

            try {
                await this.scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: 250 },
                    (decodedText) => this.onScan(decodedText),
                    () => {},
                );
            } catch (e) {
                console.error('Camera start failed', e);
                this.error = 'Ne mogu da pristupim kameri. Proverite dozvolu i HTTPS.';
                await this.stop();
            }
        },

        onScan(decodedText) {
            this.stop();
            window.location.href = decodedText;
        },

        async stop() {
            if (this.scanner) {
                try {
                    await this.scanner.stop();
                    this.scanner.clear();
                } catch (e) {
                    // Scanner may already be stopped; ignore.
                }

                this.scanner = null;
            }

            this.active = false;
        },

        destroy() {
            this.stop();
        },
    }));
});
