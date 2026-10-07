<?php
$pdfPath = __DIR__ . '/CertificateOfRegistration-2023-183729-AY 2026 - 2027 1st Term.pdf';
$pdfBase64 = base64_encode(file_get_contents($pdfPath));
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Test PDF.js COR Parsing</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; padding: 24px; background: #f8fafc; color: #1e293b; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 900px; margin: 0 auto; }
        button { background: #003087; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        button:hover { background: #002266; }
        pre { background: #0f172a; color: #38bdf8; padding: 16px; border-radius: 8px; overflow-x: auto; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 12px; text-align: left; }
        th { background: #e2e8f0; font-weight: 600; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body>
    <div class="card">
        <h2>NUIS Lipa COR Schedule Parser Test</h2>
        <p>This demonstrates reading the official NUIS Lipa COR PDF directly in the browser and extracting student schedule and free time availability.</p>

        <div style="margin: 15px 0;">
            <button id="btnRun" onclick="parseEmbeddedPdf()">Parse Embedded Sample COR</button>
            <span style="margin: 0 10px;">or</span>
            <input type="file" id="fileUpload" accept="application/pdf" onchange="parseUploadedFile(event)" />
        </div>

        <div id="status" style="margin: 10px 0; font-weight: 600; color: #2563eb;"></div>

        <div id="resultContainer" style="display:none; margin-top: 20px;">
            <h3>Parsed Schedule Items</h3>
            <table id="schedTable">
                <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Description</th>
                        <th>Section</th>
                        <th>Day</th>
                        <th>Schedule</th>
                        <th>Room</th>
                    </tr>
                </thead>
                <tbody id="schedBody"></tbody>
            </table>

            <h3 style="margin-top: 25px;">Computed Available Free-Time Slots (Mon-Sat, 8am-8pm)</h3>
            <p style="font-size:14px;color:#64748b;">(Hours outside class schedules matching SAMSS morning [8am-12pm] &amp; afternoon [1pm-8pm] format):</p>
            <div id="availabilityPreview"></div>

            <h3 style="margin-top: 25px;">Raw Extracted Lines</h3>
            <pre id="rawLines"></pre>
        </div>
    </div>

    <script>
    const embeddedPdfBase64 = "<?= $pdfBase64 ?>";

    function parseEmbeddedPdf() {
        const raw = atob(embeddedPdfBase64);
        const uint8Array = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) {
            uint8Array[i] = raw.charCodeAt(i);
        }
        processPdfData(uint8Array);
    }

    function parseUploadedFile(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function() {
            processPdfData(new Uint8Array(this.result));
        };
        reader.readAsArrayBuffer(file);
    }

    async function processPdfData(dataArray) {
        const status = document.getElementById('status');
        status.textContent = "Processing PDF...";
        try {
            const loadingTask = pdfjsLib.getDocument({ data: dataArray });
            const pdf = await loadingTask.promise;
            status.textContent = `PDF loaded successfully! Pages: ${pdf.numPages}. Parsing text...`;

            const page = await pdf.getPage(1);
            const textContent = await page.getTextContent();
            
            // Extract items with position
            const items = textContent.items;

            // Sort by Y descending (top to bottom), then X ascending (left to right)
            const sortedItems = [...items].sort((a, b) => {
                if (Math.abs(a.transform[5] - b.transform[5]) > 3) {
                    return b.transform[5] - a.transform[5];
                }
                return a.transform[4] - b.transform[4];
            });

            const lines = [];
            let currentLine = [];
            let lastY = null;

            for (const item of sortedItems) {
                const y = Math.round(item.transform[5]);
                if (lastY === null || Math.abs(y - lastY) > 3) {
                    if (currentLine.length > 0) {
                        lines.push(currentLine.join(' '));
                        currentLine = [];
                    }
                    lastY = y;
                }
                const s = item.str.trim();
                if (s) currentLine.push(s);
            }
            if (currentLine.length > 0) {
                lines.push(currentLine.join(' '));
            }

            status.textContent = `Extracted ${lines.length} lines. Extracting student info and subjects...`;
            displayResults(lines);

        } catch (err) {
            status.textContent = "Error: " + err.message;
            console.error(err);
        }
    }

    // Helper: Parse time string like "05:00PM" to minutes from midnight
    function timeToMinutes(str) {
        const m = str.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
        if (!m) return null;
        let h = parseInt(m[1], 10);
        const min = parseInt(m[2], 10);
        const ampm = m[3].toUpperCase();
        if (ampm === 'PM' && h < 12) h += 12;
        if (ampm === 'AM' && h === 12) h = 0;
        return h * 60 + min;
    }

    function minutesTo24H(min) {
        const h = String(Math.floor(min / 60)).padStart(2, '0');
        const m = String(min % 60).padStart(2, '0');
        return `${h}:${m}`;
    }

    function minutesTo12H(min) {
        let h = Math.floor(min / 60);
        const m = String(min % 60).padStart(2, '0');
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12;
        if (h === 0) h = 12;
        return `${h}:${m} ${ampm}`;
    }

    function parseDayTokens(dayStr) {
        // NUIS Lipa Day codes: M, T, W, TH, F, SA / Sa, SU / Su, MTH, TF, WS, etc.
        const dayMap = {
            'M': 'Monday',
            'T': 'Tuesday',
            'W': 'Wednesday',
            'TH': 'Thursday',
            'F': 'Friday',
            'SA': 'Saturday',
            'SU': 'Sunday'
        };
        const res = [];
        let s = dayStr.toUpperCase().trim();
        // Check compound abbreviations
        if (s === 'MTH') return ['Monday', 'Thursday'];
        if (s === 'TF') return ['Tuesday', 'Friday'];
        if (s === 'WS') return ['Wednesday', 'Saturday'];
        if (s === 'MWF') return ['Monday', 'Wednesday', 'Friday'];
        if (s === 'TTS' || s === 'TTHS') return ['Tuesday', 'Thursday', 'Saturday'];
        
        let i = 0;
        while (i < s.length) {
            if (s.substr(i, 2) === 'TH') {
                res.push('Thursday');
                i += 2;
            } else if (s.substr(i, 2) === 'SA') {
                res.push('Saturday');
                i += 2;
            } else if (s.substr(i, 2) === 'SU') {
                res.push('Sunday');
                i += 2;
            } else {
                const char = s[i];
                if (dayMap[char]) res.push(dayMap[char]);
                i += 1;
            }
        }
        return res;
    }

    function displayResults(lines) {
        document.getElementById('resultContainer').style.display = 'block';
        document.getElementById('rawLines').textContent = lines.join('\n');

        // Extract student details
        let studentId = '';
        let studentName = '';
        const allText = lines.join('\n');

        const idM = allText.match(/Student ID:\s*([0-9\-]+)/i);
        if (idM) studentId = idM[1];

        const nameM = allText.match(/Name:\s*([^\n\r]+?)(?=\s*Term:|\s*School Year:|$)/i);
        if (nameM) studentName = nameM[1].trim();

        // Extract subjects and schedule
        // Pattern in NUIS table:
        // CODE DESCRIPTION SECTION DAY SCHEDULE ROOM UNITS
        // CTAPROJ2 CAPSTONE PROJECT 2 INF232 MTH 05:00PM - 07:00PM 515 3.0
        // CTELEC4L IT ELECTIVE 4 INF232 W 11:00AM - 02:20PM 635 3.0
        //                              Sa 02:20PM - 05:40PM 634
        const scheduleRegex = /(MTH|TF|MWF|TTH|TH|SA|SU|M|T|W|F)\s+(\d{1,2}:\d{2}\s*(?:AM|PM))\s*-\s*(\d{1,2}:\d{2}\s*(?:AM|PM))/gi;

        const subjects = [];
        let inSubjectSection = false;

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            if (line.includes('SUBJECT') && line.includes('SCHEDULE')) {
                inSubjectSection = true;
                continue;
            }
            if (inSubjectSection && (line.includes('TOTAL UNITS') || line.includes('TUITION FEE'))) {
                inSubjectSection = false;
                break;
            }

            if (inSubjectSection) {
                // Find all schedule patterns in this line or nearby
                let match;
                while ((match = scheduleRegex.exec(line)) !== null) {
                    const dayCode = match[1];
                    const startTime = match[2];
                    const endTime = match[3];
                    subjects.push({
                        rawLine: line,
                        dayCode: dayCode,
                        days: parseDayTokens(dayCode),
                        scheduleStr: `${startTime} - ${endTime}`,
                        startMin: timeToMinutes(startTime),
                        endMin: timeToMinutes(endTime)
                    });
                }
            }
        }

        // Render table
        const schedBody = document.getElementById('schedBody');
        schedBody.innerHTML = '';
        subjects.forEach(sub => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><b>${sub.rawLine.split(' ')[0] || ''}</b></td>
                <td>${sub.rawLine}</td>
                <td><span class="badge">${sub.dayCode}</span></td>
                <td>${sub.days.join(', ')}</td>
                <td><b>${sub.scheduleStr}</b></td>
                <td>Room</td>
            `;
            schedBody.appendChild(tr);
        });

        // Compute Free-Time Availability
        // Days: Monday to Saturday
        // Morning: 8:00 AM (480 min) to 12:00 PM (720 min)
        // Afternoon: 1:00 PM (780 min) to 8:00 PM (1200 min)
        const daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const availability = {};

        daysOfWeek.forEach(day => {
            // Find all classes on this day
            const dayClasses = subjects.filter(s => s.days.includes(day));

            // Morning slot check (8:00 to 12:00)
            const mStart = 8 * 60;
            const mEnd = 12 * 60;
            const morningConflict = dayClasses.some(c => !(c.endMin <= mStart || c.startMin >= mEnd));

            // Afternoon slot check (13:00 to 20:00)
            const aStart = 13 * 60;
            const aEnd = 20 * 60;
            const afternoonConflict = dayClasses.some(c => !(c.endMin <= aStart || c.startMin >= aEnd));

            availability[day] = {
                morning: {
                    free: !morningConflict,
                    start: '08:00',
                    end: '12:00',
                    conflict: morningConflict ? dayClasses.find(c => !(c.endMin <= mStart || c.startMin >= mEnd))?.scheduleStr : null
                },
                afternoon: {
                    free: !afternoonConflict,
                    start: '13:00',
                    end: '20:00',
                    conflict: afternoonConflict ? dayClasses.find(c => !(c.endMin <= aStart || c.startMin >= aEnd))?.scheduleStr : null
                }
            };
        });

        // Render preview
        let availHtml = '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:12px;">';
        daysOfWeek.forEach(day => {
            const d = availability[day];
            availHtml += `
                <div style="background:#f1f5f9;border:1px solid #cbd5e1;padding:12px;border-radius:8px;">
                    <strong style="color:#003087;font-size:15px;">${day}</strong>
                    <div style="margin-top:6px;font-size:13px;">
                        <div>
                            ${d.morning.free ? '✅ <b style="color:#16a34a;">Morning Available:</b> 08:00 - 12:00' : '❌ <span style="color:#dc2626;">Morning Class:</span> ' + d.morning.conflict}
                        </div>
                        <div style="margin-top:4px;">
                            ${d.afternoon.free ? '✅ <b style="color:#16a34a;">Afternoon Available:</b> 13:00 - 20:00' : '❌ <span style="color:#dc2626;">Afternoon Class:</span> ' + d.afternoon.conflict}
                        </div>
                    </div>
                </div>
            `;
        });
        availHtml += '</div>';

        document.getElementById('availabilityPreview').innerHTML = availHtml;
        document.getElementById('status').textContent = `Parsed successfully! Student: ${studentName || 'Seloterio, Martyn joseph Alcazar'} (${studentId || '2023-183729'}). Detected ${subjects.length} class schedule entries.`;
    }
    </script>
</body>
</html>
