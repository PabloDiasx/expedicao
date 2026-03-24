/**
 * Barcode Scanner Code Converter
 *
 * Converts scanner barcode formats to the standard serial format: MODEL.YY.SERIAL
 * Shared between expedition and production modules.
 */
(function (global) {
    'use strict';

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

        if (/^[A-Z0-9]+\.[0-9]{2}\.[0-9]+$/.test(normalized)) {
            return {
                raw: normalized,
                serial: normalized,
                converted: false,
            };
        }

        var dashedMatches = normalized.match(/^([A-Z]+[0-9]+).*-([0-9]{2})\.([0-9]{1,8})$/);
        if (dashedMatches) {
            var model = dashedMatches[1];
            var year = dashedMatches[2];
            var serialNumber = String(parseInt(dashedMatches[3], 10));
            var serial = model + '.' + year + '.' + serialNumber;

            return {
                raw: normalized,
                serial: serial,
                converted: serial !== normalized,
            };
        }

        var dashedTailMatches = normalized.match(/-([0-9]{2})\.([0-9]{1,8})$/);
        if (dashedTailMatches) {
            var modelMatches = normalized.match(/^(V[0-9]{1,2})/);
            if (modelMatches) {
                var model2 = modelMatches[1];
                var year2 = dashedTailMatches[1];
                var serialNumber2 = String(parseInt(dashedTailMatches[2], 10));
                var serial2 = model2 + '.' + year2 + '.' + serialNumber2;

                return {
                    raw: normalized,
                    serial: serial2,
                    converted: serial2 !== normalized,
                };
            }
        }

        var compactMatches = normalized.match(/^([A-Z]+[0-9]+)[A-Z]+([0-9]{2})([0-9]{2,8})$/);
        if (!compactMatches) {
            return null;
        }

        var model3 = compactMatches[1];
        var year3 = compactMatches[2];
        var serialNumber3 = String(parseInt(compactMatches[3], 10));
        var serial3 = model3 + '.' + year3 + '.' + serialNumber3;

        return {
            raw: normalized,
            serial: serial3,
            converted: serial3 !== normalized,
        };
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
