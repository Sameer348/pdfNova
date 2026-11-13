/**
 * Minimal ZIP writer (no compression — "stored"), for bundling files created
 * in the browser (e.g. PDF → JPG pages). JPEGs are already compressed, so
 * storing them is fast and the ZIP is barely larger than its contents.
 *
 *   const blob = await createZip([{ name: 'page-1.jpg', blob }, ...]);
 *
 * Follows the PKWARE APPNOTE format: local headers + central directory.
 * File names are UTF-8 (general-purpose flag bit 11).
 */

const CRC_TABLE = (() => {
    const table = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
        let c = n;
        for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1;
        table[n] = c >>> 0;
    }
    return table;
})();

function crc32(bytes) {
    let crc = 0xFFFFFFFF;
    for (let i = 0; i < bytes.length; i++) crc = CRC_TABLE[(crc ^ bytes[i]) & 0xFF] ^ (crc >>> 8);
    return (crc ^ 0xFFFFFFFF) >>> 0;
}

function dosDateTime(date) {
    const time = (date.getHours() << 11) | (date.getMinutes() << 5) | Math.floor(date.getSeconds() / 2);
    const day = ((date.getFullYear() - 1980) << 9) | ((date.getMonth() + 1) << 5) | date.getDate();
    return [time, day];
}

export async function createZip(entries) {
    const encoder = new TextEncoder();
    const parts = [];
    const central = [];
    let offset = 0;
    const [time, day] = dosDateTime(new Date());

    for (const { name, blob } of entries) {
        const data = new Uint8Array(await blob.arrayBuffer());
        const nameBytes = encoder.encode(name);
        const crc = crc32(data);

        const local = new DataView(new ArrayBuffer(30));
        local.setUint32(0, 0x04034b50, true);   // local file header signature
        local.setUint16(4, 20, true);           // version needed
        local.setUint16(6, 0x0800, true);       // flags: UTF-8 names
        local.setUint16(8, 0, true);            // method: stored
        local.setUint16(10, time, true);
        local.setUint16(12, day, true);
        local.setUint32(14, crc, true);
        local.setUint32(18, data.length, true); // compressed size
        local.setUint32(22, data.length, true); // uncompressed size
        local.setUint16(26, nameBytes.length, true);
        local.setUint16(28, 0, true);           // extra length
        parts.push(local.buffer, nameBytes, data);

        const cd = new DataView(new ArrayBuffer(46));
        cd.setUint32(0, 0x02014b50, true);      // central directory signature
        cd.setUint16(4, 20, true);              // version made by
        cd.setUint16(6, 20, true);              // version needed
        cd.setUint16(8, 0x0800, true);
        cd.setUint16(10, 0, true);
        cd.setUint16(12, time, true);
        cd.setUint16(14, day, true);
        cd.setUint32(16, crc, true);
        cd.setUint32(20, data.length, true);
        cd.setUint32(24, data.length, true);
        cd.setUint16(28, nameBytes.length, true);
        cd.setUint32(42, offset, true);         // offset of local header
        central.push(cd.buffer, nameBytes);

        offset += 30 + nameBytes.length + data.length;
    }

    const centralSize = central.reduce((sum, part) => sum + (part.byteLength ?? part.length), 0);
    const end = new DataView(new ArrayBuffer(22));
    end.setUint32(0, 0x06054b50, true);         // end of central directory
    end.setUint16(8, entries.length, true);
    end.setUint16(10, entries.length, true);
    end.setUint32(12, centralSize, true);
    end.setUint32(16, offset, true);

    return new Blob([...parts, ...central, end.buffer], { type: 'application/zip' });
}
