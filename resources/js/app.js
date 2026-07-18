import QRCode from 'qrcode';

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
});
