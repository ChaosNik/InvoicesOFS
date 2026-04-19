@page {
    margin: 5mm;
}

@media print {
    body {
        padding: 0;
    }
}

@media screen {
    body {
        box-sizing: border-box;
        padding: 5mm;
    }
}

html,
body {
    margin: 0;
}

body {
    color: #111;
    font-family: "DejaVu Sans";
    font-size: 10px;
    line-height: 1.28;
}

table {
    border-collapse: collapse;
}

.compact-document {
    width: 100%;
}

.compact-header-table {
    width: 100%;
    margin: 0 0 13px 0;
}

.compact-header-table td {
    vertical-align: top;
}

.compact-party-block {
    font-size: 10px;
    line-height: 1.35;
    word-wrap: break-word;
}

.compact-party-block h1,
.compact-party-block h2,
.compact-party-block h3,
.compact-party-block p {
    margin: 0 0 1px 0;
    padding: 0;
}

.compact-party-title {
    font-weight: bold;
    margin-bottom: 3px;
}

.compact-customer-block {
    text-align: right;
}

.compact-logo {
    display: block;
    max-height: 38px;
    max-width: 170px;
    margin-bottom: 5px;
}

.compact-meta {
    width: 100%;
    margin: 8px auto 0 auto;
    text-align: center;
}

.compact-meta table {
    margin: 0 auto;
}

.compact-meta td {
    font-size: 10px;
    line-height: 1.25;
    padding: 1px 4px;
}

.compact-meta-label {
    font-weight: bold;
    text-align: right;
    white-space: nowrap;
}

.compact-meta-value {
    min-width: 115px;
    border-bottom: 1px solid #111;
    text-align: center;
}

.compact-ofs-row .compact-meta-label,
.compact-ofs-row .compact-meta-value {
    font-weight: bold;
}

.compact-bank-table {
    width: 100%;
    margin: 0 0 8px 0;
    table-layout: fixed;
}

.compact-bank-table th,
.compact-bank-table td {
    border: 1px solid #111;
    font-size: 9px;
    line-height: 1.2;
    padding: 2px 4px;
}

.compact-bank-table th {
    border-top: 2px solid #111;
    font-style: italic;
    text-align: center;
}

.compact-bank-label {
    width: 18%;
    font-weight: bold;
    white-space: nowrap;
}

.compact-bank-value {
    width: 32%;
}

.items-table {
    width: 100%;
    margin: 0 auto;
    table-layout: fixed;
    page-break-inside: auto;
}

.items-table th,
.items-table td {
    border: 1px solid #111;
    font-size: 9px;
    line-height: 1.2;
    padding: 2px 4px;
    vertical-align: top;
}

.items-table th {
    font-style: italic;
    font-weight: bold;
    text-align: center;
}

.item-table-heading-row th {
    border-top: 2px solid #111;
}

.item-table-heading-row th:first-child,
.item-row td:first-child,
.summary-row td:first-child,
.total-row td:first-child {
    border-left: 2px solid #111;
}

.item-table-heading-row th:last-child,
.item-row td:last-child,
.summary-row td:last-child,
.total-row td:last-child {
    border-right: 2px solid #111;
}

.item-row:last-of-type td {
    border-bottom: 1px solid #111;
}

.item-cell {
    color: #111;
}

.item-description {
    display: block;
    color: #333;
    font-size: 8px;
    line-height: 1.25;
    margin-top: 1px;
}

.ofs-item-meta {
    display: block;
    color: #111;
    font-size: 8px;
    line-height: 1.25;
    margin-top: 1px;
}

.summary-row td {
    font-size: 9px;
    padding: 2px 4px;
}

.summary-label {
    font-weight: bold;
    text-align: right;
}

.summary-value {
    text-align: right;
}

.total-row td {
    border-bottom: 2px solid #111;
    font-size: 10px;
    font-weight: bold;
}

.compact-signatures {
    width: 74%;
    margin: 8px auto 0 auto;
}

.compact-signatures td {
    width: 50%;
    padding: 0 20px;
    text-align: center;
    font-size: 9px;
    font-style: italic;
}

.signature-line {
    border-top: 2px solid #111;
    height: 12px;
    margin-top: 7px;
}

.notes {
    width: 100%;
    margin-top: 16px;
    font-size: 9px;
    line-height: 1.35;
    page-break-inside: avoid;
}

.notes-label {
    font-weight: bold;
    margin-bottom: 3px;
}

.text-left {
    text-align: left;
}

.text-center {
    text-align: center;
}

.text-right {
    text-align: right;
}

.nowrap {
    white-space: nowrap;
}
