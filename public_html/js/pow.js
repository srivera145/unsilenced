/* Proof of work for the report form (Keel\App\Services\Survivor\ProofOfWork).
 *
 * Finds a number n such that SHA-256("challenge:n") starts with `bits` zero
 * bits. The challenge is 32 hex characters, so the message always fits one
 * 64-byte SHA-256 block, and this is a small single-block SHA-256 written for
 * that. It is our own code: no library, nothing from another site. WebCrypto
 * is not used because it is missing on plain-HTTP pages and is slower per
 * call for millions of tiny hashes.
 *
 * Runs in slices so the page stays responsive and can show progress.
 *   window.UnsilencedPow.solve(challenge, bits, onProgress) -> Promise<string>
 */
(function () {
    var K = [
        0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
        0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
        0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
        0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
        0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
        0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
        0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
        0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
    ];
    var W = new Int32Array(64);
    var bytes = new Uint8Array(64);

    // The first two 32-bit words of SHA-256 of bytes[0..length).
    function digestHead(length) {
        var i;
        for (i = length; i < 64; i++) {
            bytes[i] = 0;
        }
        bytes[length] = 0x80;
        for (i = 0; i < 14; i++) {
            W[i] = (bytes[i * 4] << 24) | (bytes[i * 4 + 1] << 16) | (bytes[i * 4 + 2] << 8) | bytes[i * 4 + 3];
        }
        W[14] = 0;
        W[15] = length * 8;
        for (i = 16; i < 64; i++) {
            var x = W[i - 15];
            var y = W[i - 2];
            var s0 = ((x >>> 7) | (x << 25)) ^ ((x >>> 18) | (x << 14)) ^ (x >>> 3);
            var s1 = ((y >>> 17) | (y << 15)) ^ ((y >>> 19) | (y << 13)) ^ (y >>> 10);
            W[i] = (W[i - 16] + s0 + W[i - 7] + s1) | 0;
        }

        var a = 0x6a09e667, b = 0xbb67ae85, c = 0x3c6ef372, d = 0xa54ff53a;
        var e = 0x510e527f, f = 0x9b05688c, g = 0x1f83d9ab, h = 0x5be0cd19;
        for (i = 0; i < 64; i++) {
            var S1 = ((e >>> 6) | (e << 26)) ^ ((e >>> 11) | (e << 21)) ^ ((e >>> 25) | (e << 7));
            var ch = (e & f) ^ (~e & g);
            var t1 = (h + S1 + ch + K[i] + W[i]) | 0;
            var S0 = ((a >>> 2) | (a << 30)) ^ ((a >>> 13) | (a << 19)) ^ ((a >>> 22) | (a << 10));
            var maj = (a & b) ^ (a & c) ^ (b & c);
            var t2 = (S0 + maj) | 0;
            h = g;
            g = f;
            f = e;
            e = (d + t1) | 0;
            d = c;
            c = b;
            b = a;
            a = (t1 + t2) | 0;
        }

        return [(a + 0x6a09e667) | 0, (b + 0xbb67ae85) | 0];
    }

    function leadingZeroBits(head) {
        var first = Math.clz32(head[0]);
        return first < 32 ? first : 32 + Math.clz32(head[1]);
    }

    function solve(challenge, bits, onProgress) {
        var prefix = challenge + ':';
        for (var i = 0; i < prefix.length; i++) {
            bytes[i] = prefix.charCodeAt(i) & 0xff;
        }

        return new Promise(function (resolve) {
            var nonce = 0;
            var expected = Math.pow(2, bits);

            function slice() {
                var stop = nonce + 25000;
                for (; nonce < stop; nonce++) {
                    var digits = String(nonce);
                    for (var j = 0; j < digits.length; j++) {
                        bytes[prefix.length + j] = digits.charCodeAt(j);
                    }
                    if (leadingZeroBits(digestHead(prefix.length + digits.length)) >= bits) {
                        resolve(digits);
                        return;
                    }
                }
                if (onProgress) {
                    onProgress(Math.min(0.95, nonce / (expected * 2)));
                }
                setTimeout(slice, 0);
            }

            slice();
        });
    }

    window.UnsilencedPow = { solve: solve, leadingZeroBits: function (hexHash) {
        return leadingZeroBits([parseInt(hexHash.slice(0, 8), 16) | 0, parseInt(hexHash.slice(8, 16), 16) | 0]);
    } };
})();
