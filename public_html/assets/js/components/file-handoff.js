/**
 * FileHandoff — passes files chosen on one page (e.g. the homepage quick
 * start) to a tool page, entirely inside the visitor's browser (IndexedDB).
 * Nothing is uploaded by this module.
 *
 *   await saveHandoff('merge-pdf', fileList)   // before navigating
 *   const files = await takeHandoff('merge-pdf') // on the tool page; [] if none
 *
 * A handoff is single-use, tied to one tool, and expires after 10 minutes.
 * If IndexedDB is unavailable (some private-browsing modes) both functions
 * fail quietly and the tool page simply asks for the file again.
 */

const DB_NAME = 'pdfnova';
const STORE = 'handoff';
const KEY = 'pending';
const MAX_AGE_MS = 10 * 60 * 1000;

function openDb() {
    return new Promise((resolve, reject) => {
        if (!('indexedDB' in window)) {
            reject(new Error('IndexedDB unavailable'));
            return;
        }
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => request.result.createObjectStore(STORE);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function withStore(mode, fn) {
    const db = await openDb();
    try {
        return await new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, mode);
            const result = fn(tx.objectStore(STORE));
            tx.oncomplete = () => resolve(result?.result ?? result);
            tx.onerror = () => reject(tx.error);
            tx.onabort = () => reject(tx.error);
        });
    } finally {
        db.close();
    }
}

/** Store files for a tool page. Returns true on success. */
export async function saveHandoff(tool, files) {
    try {
        const list = [...files].map((file) => ({ blob: file, name: file.name, type: file.type, lastModified: file.lastModified }));
        await withStore('readwrite', (store) => store.put({ tool, files: list, savedAt: Date.now() }, KEY));
        return true;
    } catch {
        return false;
    }
}

/** Retrieve (and delete) files handed to this tool. Always resolves to an array of File objects. */
export async function takeHandoff(tool) {
    try {
        const record = await withStore('readonly', (store) => store.get(KEY));
        if (!record) {
            return [];
        }
        const expired = Date.now() - record.savedAt > MAX_AGE_MS;
        if (record.tool !== tool && !expired) {
            return []; // meant for another tool page — leave it alone
        }
        await withStore('readwrite', (store) => store.delete(KEY)); // single use
        if (expired) {
            return [];
        }
        return record.files.map((f) => new File([f.blob], f.name, { type: f.type, lastModified: f.lastModified }));
    } catch {
        return [];
    }
}
