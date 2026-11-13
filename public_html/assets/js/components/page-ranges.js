/**
 * Page-range parsing shared by tool pages (Split, Rotate, Organize...).
 * Mirrors services/pdf/PageRangeParser.php — same rules, same messages.
 */
const MAX_RANGES = 200;

/** Parse "1-3, 5, 8-" → [[1,3],[5,5],[8,N]] or throw Error(message). */
export function parseRanges(input, pageCount) {
    const text = String(input).replace(/[–—]/g, '-').trim();
    if (!text) throw new Error('Enter at least one page or range, for example 1-3.');
    const ranges = [];
    for (let part of text.split(/[,;]+/)) {
        part = part.replace(/\s+/g, '');
        if (!part) continue;
        let m;
        let start;
        let end;
        if ((m = part.match(/^(\d{1,6})$/))) { start = end = Number(m[1]); }
        else if ((m = part.match(/^(\d{1,6})-(\d{1,6})$/))) { start = Number(m[1]); end = Number(m[2]); }
        else if ((m = part.match(/^(\d{1,6})-$/))) { start = Number(m[1]); end = pageCount; }
        else throw new Error(`"${part}" isn't a valid page or range. Use formats like 3, 1-5 or 8-.`);
        if (start < 1) throw new Error('Page numbers start at 1.');
        if (start > end) throw new Error(`The range ${start}-${end} is backwards. Write it as ${end}-${start}.`);
        if (end > pageCount) {
            const missing = start > pageCount ? start : end;
            throw new Error(`Page ${missing} doesn't exist — this PDF has ${pageCount} ${pageCount === 1 ? 'page' : 'pages'}.`);
        }
        ranges.push([start, end]);
        if (ranges.length > MAX_RANGES) throw new Error(`Please use at most ${MAX_RANGES} ranges at once.`);
    }
    if (!ranges.length) throw new Error('Enter at least one page or range, for example 1-3.');
    return ranges;
}

