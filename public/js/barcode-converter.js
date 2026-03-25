/**
 * Barcode Scanner Code Converter
 *
 * Converts scanner barcode formats to the standard serial format: MODEL.YY.SERIAL
 * Shared between expedition and production modules.
 *
 * Known model prefixes from NFs: V1, V12, V2CROSS, V2R, V4, V4N, V5P, V5PT, V5X, V5XT, V8X
 * Barcode pattern: {MODEL}{VARIANT_COLORS}{DIGITS}APX-{YY}.{SERIAL}
 * Example: V8XBBFBBB09159301529APX-26.679 → V8X.26.679
 */
(function (global) {
    'use strict';

    // Known model prefixes sorted longest-first for greedy match
    var KNOWN_MODELS = [
        'V2CROSS', 'V5XT', 'V5PT',
        'V8X', 'V5P', 'V5X', 'V4N', 'V2R', 'V12',
        'V1', 'V2', 'V4', 'V5', 'V8'
    ];

    /**
     * Extract model prefix from raw barcode.
     * Tries known models first, then falls back to regex.
     */
    function extractModel(barcode) {
        var upper = barcode.toUpperCase();
        for (var i = 0; i < KNOWN_MODELS.length; i++) {
            if (upper.indexOf(KNOWN_MODELS[i]) === 0) {
                return KNOWN_MODELS[i];
            }
        }
        // Fallback: letters + digits + optional trailing letters (e.g. V8X, V4N)
        var m = upper.match(/^(V[0-9]{1,2}[A-Z]*)/);
        if (m) return m[1];
        // Generic fallback
        var g = upper.match(/^([A-Z]+[0-9]+[A-Z]*)/);
        return g ? g[1] : null;
    }

    /**
     * @param {string} rawValue - Raw barcode value from scanner input
     * @returns {{ raw: string, serial: string, converted: boolean } | null}
     */
    function convertScannerCodeToSerial(rawValue) {
        var normalized = String(rawValue || '')
            .trim()
            .toUpperCase()
            .replace(/\s+/g, '');

        if (!normalized) {
            return null;
        }

        // Already in final format: MODEL.YY.SERIAL
        if (/^[A-Z0-9]+\.[0-9]{2}\.[0-9]+$/.test(normalized)) {
            return {
                raw: normalized,
                serial: normalized,
                converted: false,
            };
        }

        // Format: {MODEL}{STUFF}-{YY}.{SERIAL} (dashed with tail)
        var dashedTail = normalized.match(/-([0-9]{2})\.([0-9]{1,8})$/);
        if (dashedTail) {
            var model = extractModel(normalized);
            if (model) {
                var year = dashedTail[1];
                var serialNumber = String(parseInt(dashedTail[2], 10));
                var serial = model + '.' + year + '.' + serialNumber;
                return {
                    raw: normalized,
                    serial: serial,
                    converted: serial !== normalized,
                };
            }
        }

        // Compact format: {MODEL}{LETTERS}{YY}{SERIAL} (no dashes)
        var compactModel = extractModel(normalized);
        if (compactModel) {
            var rest = normalized.substring(compactModel.length);
            var compactMatch = rest.match(/[A-Z]+([0-9]{2})([0-9]{2,8})$/);
            if (compactMatch) {
                var serial2 = compactModel + '.' + compactMatch[1] + '.' + String(parseInt(compactMatch[2], 10));
                return {
                    raw: normalized,
                    serial: serial2,
                    converted: serial2 !== normalized,
                };
            }
        }

        return null;
    }

    /**
     * @param {string} notes - Current notes value
     * @param {{ raw: string, serial: string, converted: boolean }} parsed
     * @returns {string} Updated notes with conversion label appended
     */
    function appendConversionNote(notes, parsed) {
        if (!parsed || !parsed.converted) {
            return notes;
        }

        var conversionLabel = 'Codigo lido: ' + parsed.raw + ' | Serial convertido: ' + parsed.serial;
        var current = String(notes || '').trim();

        if (current.includes(conversionLabel)) {
            return current;
        }

        return current === '' ? conversionLabel : current + ' | ' + conversionLabel;
    }

    global.BarcodeConverter = {
        convert: convertScannerCodeToSerial,
        appendConversionNote: appendConversionNote,
    };
})(window);
