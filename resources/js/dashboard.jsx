import {
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import axios from 'axios';
import NepaliDateModule from 'nepali-date-converter';
import { NepaliDatePicker } from 'nepali-datepicker-reactjs';
import 'nepali-datepicker-reactjs/dist/index.css';
import {
    startTransition,
    useEffect,
    useRef,
    useState,
} from 'react';

const NepaliDate = NepaliDateModule.default ?? NepaliDateModule;
const NEPAL_TIME_ZONE = 'Asia/Kathmandu';

const currencyFormatter = new Intl.NumberFormat('en-NP', {
    style: 'currency',
    currency: 'NPR',
    maximumFractionDigits: 2,
});

const numberFormatter = new Intl.NumberFormat('en-US');

const englishDateTimeFormatter = new Intl.DateTimeFormat('en-NP', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: NEPAL_TIME_ZONE,
});

const nepaliDateTimeFormatter = new Intl.DateTimeFormat('ne-NP', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: NEPAL_TIME_ZONE,
});

const englishTimeFormatter = new Intl.DateTimeFormat('en-NP', {
    timeStyle: 'short',
    timeZone: NEPAL_TIME_ZONE,
});

const nepaliTimeFormatter = new Intl.DateTimeFormat('ne-NP', {
    timeStyle: 'short',
    timeZone: NEPAL_TIME_ZONE,
});

const emptyCatalog = {
    filters: {
        tenants: [],
        hospitals: [],
        pharmacies: [],
        categories: [],
        suppliers: [],
        medicines: [],
        payment_statuses: [],
        payment_methods: [],
        formats: [],
    },
    stats: {
        tenants: 0,
        hospitals: 0,
        pharmacies: 0,
        medicines: 0,
        sales: 0,
        sale_items: 0,
        latest_sale_at: null,
    },
    recent_exports: [],
};

const emptyPreview = {
    summary: {
        total_rows: 0,
        total_units: 0,
        total_revenue: 0,
    },
    rows: [],
    pagination: {
        current_page: 1,
        per_page: 10,
        total: 0,
        last_page: 1,
        from: 0,
        to: 0,
    },
};

const emptyPharmacyList = {
    items: [],
    pagination: {
        current_page: 1,
        per_page: 8,
        total: 0,
        last_page: 1,
        from: 0,
        to: 0,
    },
};

const emptySalesList = {
    items: [],
    summary: {
        total_rows: 0,
        total_quantity: 0,
        total_revenue: 0,
        latest_sale_at: null,
    },
    pagination: {
        current_page: 1,
        per_page: 10,
        total: 0,
        last_page: 1,
        from: 0,
        to: 0,
    },
};

const tabs = [
    { id: 'dashboard', label: 'Dashboard', path: '/dashboard' },
    { id: 'overview', label: 'Reports', path: '/reports' },
    { id: 'sales', label: 'Sales', path: '/sales' },
    { id: 'pharmacies', label: 'Pharmacies', path: '/pharmacies' },
    { id: 'exports', label: 'Exports', path: '/exports' },
];

function resolveTabFromPath(pathname) {
    if (!pathname || pathname === '/') {
        return 'dashboard';
    }

    return tabs.find((tab) => tab.path === pathname)?.id ?? 'dashboard';
}

function resolvePathFromTab(tabId) {
    return tabs.find((tab) => tab.id === tabId)?.path ?? '/dashboard';
}

function isoDate(daysBack = 0) {
    const value = new Date();
    value.setDate(value.getDate() - daysBack);

    return value.toISOString().slice(0, 10);
}

function initialFilters() {
    return {
        date_from: isoDate(14),
        date_to: isoDate(0),
        tenant_id: '',
        hospital_id: '',
        pharmacy_id: '',
        category_id: '',
        supplier_id: '',
        payment_status: '',
        cold_chain: '',
        format: 'csv',
        page: 1,
        per_page: 10,
    };
}

function initialPharmacyFilters() {
    return {
        page: 1,
        per_page: 8,
        search: '',
        tenant_id: '',
        hospital_id: '',
    };
}

function initialSalesFilters() {
    return {
        date_from: isoDate(14),
        date_to: isoDate(0),
        search: '',
        tenant_id: '',
        hospital_id: '',
        pharmacy_id: '',
        medicine_id: '',
        payment_status: '',
        payment_method: '',
        page: 1,
        per_page: 10,
    };
}

function initialPharmacyForm() {
    return {
        hospital_id: '',
        code: '',
        name: '',
        license_number: '',
        contact_email: '',
        area: 'Baneshwor',
        city: 'Kathmandu',
        district: 'Kathmandu',
        province: 'Bagmati',
        postal_code: '44600',
        email_domain: 'hospital.com.np',
        seed_demo_sale: true,
    };
}

function initialSaleForm() {
    return {
        pharmacy_id: '',
        medicine_id: '',
        invoice_number: '',
        payment_method: 'cash',
        payment_status: 'paid',
        sold_at: formatDateTimeForInput(new Date()),
        batch_number: '',
        quantity: '1',
        unit_price: '',
        discount_amount: '0',
        tax_amount: '',
        expires_at: '',
    };
}

function cleanPayload(filters, options = {}) {
    const { includeFormat = false, includePagination = false } = options;

    return Object.fromEntries(
        Object.entries(filters).filter(([key, value]) => {
            if (key === 'format' && !includeFormat) {
                return false;
            }

            if ((key === 'page' || key === 'per_page') && !includePagination) {
                return false;
            }

            return value !== '' && value !== null && value !== undefined;
        })
    );
}

function formatCount(value) {
    return numberFormatter.format(Number(value ?? 0));
}

function formatCurrency(value) {
    return currencyFormatter.format(Number(value ?? 0));
}

function formatDateTime(value) {
    if (!value) {
        return 'Not available';
    }

    return englishDateTimeFormatter.format(new Date(value));
}

function formatNepaliDateTime(value) {
    if (!value) {
        return 'उपलब्ध छैन';
    }

    return nepaliDateTimeFormatter.format(new Date(value));
}

function formatTime(value, locale = 'en') {
    if (!value) {
        return 'Not available';
    }

    return locale === 'np'
        ? nepaliTimeFormatter.format(new Date(value))
        : englishTimeFormatter.format(new Date(value));
}

function formatNepalIso(date) {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: NEPAL_TIME_ZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(date);

    const lookup = Object.fromEntries(parts.filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]));

    return `${lookup.year}-${lookup.month}-${lookup.day}`;
}

function formatDateTimeForInput(value) {
    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const pad = (part) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function parseAdDateToNepalDate(iso) {
    if (!iso) {
        return null;
    }

    return new Date(`${iso}T00:00:00+05:45`);
}

function toBsIso(value) {
    if (!value) {
        return '';
    }

    const source = value.includes('T') ? new Date(value) : parseAdDateToNepalDate(value);

    if (!source || Number.isNaN(source.getTime())) {
        return '';
    }

    try {
        return new NepaliDate(source).format('YYYY-MM-DD');
    } catch {
        return '';
    }
}

function toBsLabel(value, language = 'en') {
    if (!value) {
        return '';
    }

    const source = value.includes('T') ? new Date(value) : parseAdDateToNepalDate(value);

    if (!source || Number.isNaN(source.getTime())) {
        return '';
    }

    try {
        return new NepaliDate(source).format('ddd, DD MMMM YYYY', language === 'np' ? 'np' : 'en');
    } catch {
        return '';
    }
}

function toAdIso(bsValue) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(bsValue)) {
        return '';
    }

    try {
        return formatNepalIso(new NepaliDate(bsValue).toJsDate());
    } catch {
        return '';
    }
}

function statusTone(status) {
    switch (status) {
        case 'completed':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700';
        case 'processing':
            return 'border-amber-200 bg-amber-50 text-amber-700';
        case 'failed':
            return 'border-rose-200 bg-rose-50 text-rose-700';
        default:
            return 'border-sky-200 bg-sky-50 text-sky-700';
    }
}

function paymentTone(status) {
    switch (status) {
        case 'paid':
            return 'bg-emerald-100 text-emerald-700';
        case 'insurance':
            return 'bg-cyan-100 text-cyan-700';
        case 'partial':
            return 'bg-amber-100 text-amber-700';
        default:
            return 'bg-rose-100 text-rose-700';
    }
}

function noticeTone(tone) {
    switch (tone) {
        case 'error':
            return 'border-rose-200 bg-rose-50 text-rose-800';
        case 'success':
            return 'border-emerald-200 bg-emerald-50 text-emerald-800';
        default:
            return 'border-sky-200 bg-sky-50 text-sky-800';
    }
}

function exportPhaseLabel(item) {
    const phase = item?.phase ?? item?.metrics?.phase;

    switch (phase) {
        case 'counted':
            return 'Counting rows';
        case 'extracting':
            return 'Pulling rows from Postgres';
        case 'building-workbook':
            return 'Building the Excel workbook';
        case 'ready':
            return 'File ready';
        default:
            if (item?.status === 'processing') {
                return 'Preparing export';
            }

            if (item?.status === 'completed') {
                return 'Ready to download';
            }

            if (item?.status === 'failed') {
                return 'Export failed';
            }

            return 'Waiting to start';
    }
}

function roleLabel(role) {
    switch (role) {
        case 'platform_admin':
            return 'Platform Admin';
        case 'tenant_admin':
            return 'Organization Admin';
        case 'hospital_admin':
            return 'Hospital Admin';
        default:
            return 'Workspace User';
    }
}

function extractError(error, fallback) {
    const responseError = error?.response?.data?.errors;

    if (responseError && typeof responseError === 'object') {
        const firstMessage = Object.values(responseError).flat()[0];

        if (firstMessage) {
            return firstMessage;
        }
    }

    return error?.response?.data?.message ?? fallback;
}

function extractFilename(disposition, fallbackName) {
    if (!disposition) {
        return fallbackName;
    }

    const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);

    if (utf8Match?.[1]) {
        return decodeURIComponent(utf8Match[1]);
    }

    const standardMatch = disposition.match(/filename="?([^"]+)"?/i);

    return standardMatch?.[1] ?? fallbackName;
}

async function extractDownloadError(error, fallback) {
    const blob = error?.response?.data;

    if (blob instanceof Blob) {
        try {
            const text = await blob.text();
            const parsed = JSON.parse(text);

            if (parsed?.errors && typeof parsed.errors === 'object') {
                const firstMessage = Object.values(parsed.errors).flat()[0];

                if (firstMessage) {
                    return firstMessage;
                }
            }

            if (parsed?.message) {
                return parsed.message;
            }
        } catch {
            return fallback;
        }
    }

    return extractError(error, fallback);
}

function startFileDownload(url) {
    const link = document.createElement('a');
    link.href = url;
    document.body.appendChild(link);
    link.click();
    link.remove();
}

function ExportCard({ item }) {
    return (
        <div className="rounded-3xl border border-slate-200/80 bg-white/80 p-5 shadow-[0_16px_40px_rgba(15,23,42,0.06)]">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-sm font-semibold text-slate-900">{item.file_name ?? `${item.format.toUpperCase()} export`}</p>
                    <p className="mt-1 text-xs text-slate-500">{formatDateTime(item.created_at)}</p>
                </div>
                <span className={`rounded-full border px-3 py-1 text-xs font-semibold capitalize ${statusTone(item.status)}`}>
                    {item.status}
                </span>
            </div>

            <p className="mt-3 text-sm text-slate-600">{exportPhaseLabel(item)}</p>

            <div className="mt-4 grid gap-3 sm:grid-cols-3">
                <div className="rounded-2xl bg-slate-50 px-3 py-3">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-slate-500">Rows</p>
                    <p className="mt-2 text-base font-semibold text-slate-900">
                        {item.requested_rows ? formatCount(item.requested_rows) : 'Counting'}
                    </p>
                </div>
                <div className="rounded-2xl bg-slate-50 px-3 py-3">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-slate-500">Strategy</p>
                    <p className="mt-2 text-base font-semibold text-slate-900">{item.metrics?.strategy ?? 'Queued'}</p>
                </div>
                <div className="rounded-2xl bg-slate-50 px-3 py-3">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-slate-500">Duration</p>
                    <p className="mt-2 text-base font-semibold text-slate-900">
                        {item.metrics?.duration_ms ? `${formatCount(item.metrics.duration_ms)} ms` : 'Running'}
                    </p>
                </div>
            </div>

            <div className="mt-4 flex items-center justify-between text-sm text-slate-600">
                <span>{item.progress}% complete</span>
                <span>{item.exported_rows ? `${formatCount(item.exported_rows)} written` : 'Waiting for worker'}</span>
            </div>

            <div className="mt-2 h-2 rounded-full bg-slate-100">
                <div
                    className="h-2 rounded-full bg-gradient-to-r from-teal-500 to-cyan-500 transition-all duration-500"
                    style={{ width: `${item.progress}%` }}
                />
            </div>

            {item.error_message ? <p className="mt-4 text-sm text-rose-600">{item.error_message}</p> : null}

            {item.download_url ? (
                <a
                    className="mt-4 inline-flex items-center rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                    href={item.download_url}
                >
                    Download {item.format.toUpperCase()}
                </a>
            ) : null}
        </div>
    );
}

function MetricTile({ label, value, helper }) {
    return (
        <div className="metric-card">
            <p className="metric-label">{label}</p>
            <p className="metric-value">{value}</p>
            {helper ? <p className="mt-2 text-sm text-slate-500">{helper}</p> : null}
        </div>
    );
}

function TabButton({ active, children, onClick }) {
    return (
        <button
            className={`tab-chip ${active ? 'tab-chip-active' : ''}`}
            onClick={onClick}
            type="button"
        >
            {children}
        </button>
    );
}

function ModalShell({ children, onClose, open, title, widthClass = 'max-w-4xl' }) {
    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 px-4 py-6">
            <div className={`w-full ${widthClass} rounded-[2rem] border border-slate-200/80 bg-white shadow-[0_32px_100px_rgba(15,23,42,0.22)]`}>
                <div className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                    <h3 className="text-xl font-semibold text-slate-950">{title}</h3>
                    <button
                        className="rounded-full border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        onClick={onClose}
                        type="button"
                    >
                        Close
                    </button>
                </div>
                <div className="max-h-[80vh] overflow-y-auto p-6">
                    {children}
                </div>
            </div>
        </div>
    );
}

function DataTable({
    columns,
    data,
    loading,
    emptyMessage,
    pagination,
    onPageChange,
    onPerPageChange,
}) {
    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: Boolean(pagination),
        pageCount: pagination ? pagination.last_page : undefined,
        state: pagination
            ? {
                  pagination: {
                      pageIndex: Math.max(0, pagination.current_page - 1),
                      pageSize: pagination.per_page,
                  },
              }
            : undefined,
        getRowId: (row, index) => String(row.sale_item_id ?? row.id ?? row.invoice_number ?? index),
    });

    return (
        <div className="data-table-shell">
            <div className="overflow-x-auto">
                <table className="min-w-full border-separate border-spacing-0 text-left text-sm">
                    <thead>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <tr key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <th key={header.id} className="border-b border-slate-200 px-4 py-4 font-semibold text-slate-500">
                                        {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                    </th>
                                ))}
                            </tr>
                        ))}
                    </thead>
                    <tbody>
                        {table.getRowModel().rows.map((row) => (
                            <tr className="align-top transition hover:bg-slate-50/80" key={row.id}>
                                {row.getVisibleCells().map((cell) => (
                                    <td className="border-b border-slate-100 px-4 py-4 text-slate-700" key={cell.id}>
                                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {!data.length && !loading ? (
                <div className="px-4 py-12 text-center text-sm text-slate-500">{emptyMessage}</div>
            ) : null}

            {pagination ? (
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-4">
                    <div className="text-sm text-slate-500">
                        Showing {formatCount(pagination.from)}-{formatCount(pagination.to)} of {formatCount(pagination.total)}
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <label className="text-sm text-slate-500">
                            <span className="mr-2">Rows</span>
                            <select
                                className="rounded-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                onChange={(event) => {
                                    onPerPageChange?.(Number(event.target.value));
                                }}
                                value={pagination.per_page}
                            >
                                {[5, 10, 25, 50].map((size) => (
                                    <option key={size} value={size}>
                                        {size}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <button
                            className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                            disabled={pagination.current_page <= 1 || loading}
                            onClick={() => {
                                onPageChange?.(pagination.current_page - 1);
                            }}
                            type="button"
                        >
                            Previous
                        </button>
                        <div className="rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">
                            Page {formatCount(pagination.current_page)} / {formatCount(pagination.last_page)}
                        </div>
                        <button
                            className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                            disabled={pagination.current_page >= pagination.last_page || loading}
                            onClick={() => {
                                onPageChange?.(pagination.current_page + 1);
                            }}
                            type="button"
                        >
                            Next
                        </button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}

export default function Dashboard({ currentUser, onLogout }) {
    const downloadedExportsRef = useRef(new Set());
    const [activeTab, setActiveTab] = useState(() => resolveTabFromPath(window.location.pathname));
    const [catalog, setCatalog] = useState(emptyCatalog);
    const [filters, setFilters] = useState(initialFilters);
    const [preview, setPreview] = useState(emptyPreview);
    const [notice, setNotice] = useState(null);
    const [dashboardLoading, setDashboardLoading] = useState(true);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [submittingExport, setSubmittingExport] = useState(false);
    const [activeExport, setActiveExport] = useState(null);
    const [activeExportId, setActiveExportId] = useState(null);
    const [bsRange, setBsRange] = useState({
        date_from_bs: toBsIso(initialFilters().date_from),
        date_to_bs: toBsIso(initialFilters().date_to),
    });
    const [pharmacies, setPharmacies] = useState(emptyPharmacyList);
    const [pharmacyLoading, setPharmacyLoading] = useState(false);
    const [pharmacySubmitting, setPharmacySubmitting] = useState(false);
    const [pharmacyFilters, setPharmacyFilters] = useState(initialPharmacyFilters);
    const [pharmacyForm, setPharmacyForm] = useState(initialPharmacyForm);
    const [editingPharmacyId, setEditingPharmacyId] = useState(null);
    const [pharmacyModalOpen, setPharmacyModalOpen] = useState(false);
    const [deleteModalItem, setDeleteModalItem] = useState(null);
    const [sales, setSales] = useState(emptySalesList);
    const [salesLoading, setSalesLoading] = useState(false);
    const [salesSubmitting, setSalesSubmitting] = useState(false);
    const [salesFilters, setSalesFilters] = useState(initialSalesFilters);
    const [saleForm, setSaleForm] = useState(initialSaleForm);
    const [editingSaleItemId, setEditingSaleItemId] = useState(null);
    const [saleModalOpen, setSaleModalOpen] = useState(false);
    const [deleteSaleModalItem, setDeleteSaleModalItem] = useState(null);

    function autoDownloadExport(exportRecord) {
        if (!exportRecord?.download_url || downloadedExportsRef.current.has(exportRecord.id)) {
            return;
        }

        downloadedExportsRef.current.add(exportRecord.id);
        startFileDownload(exportRecord.download_url);
    }

    async function loadOptionsEvent() {
        const response = await axios.get('/api/v1/reporting/options');
        startTransition(() => {
            setCatalog(response.data.data);
        });
    }

    async function loadPreviewEvent(nextFilters = filters) {
        setPreviewLoading(true);

        try {
            const response = await axios.get('/api/v1/reporting/preview', {
                params: cleanPayload(nextFilters, { includePagination: true }),
            });

            startTransition(() => {
                setPreview(response.data.data);
            });

            if ((response.data.data?.summary?.total_rows ?? 0) > 0) {
                void warmReportExport(nextFilters);
            }
        } catch (error) {
            setNotice({
                tone: 'error',
                message: extractError(error, 'Preview failed. Please check the selected filters.'),
            });
        } finally {
            setPreviewLoading(false);
        }
    }

    async function warmReportExport(nextFilters = filters) {
        try {
            const response = await axios.post(
                '/api/v1/reporting/exports',
                cleanPayload(
                    {
                        ...nextFilters,
                        format: 'xlsx',
                    },
                    { includeFormat: true }
                )
            );

            const exportRecord = response.data.data;

            setActiveExport(exportRecord);

            if (exportRecord.status === 'completed') {
                setActiveExportId(null);
            } else {
                setActiveExportId(exportRecord.id);
            }
        } catch {
            // Preview should still succeed even if background warming fails.
        }
    }

    async function loadPharmaciesEvent(nextFilters = pharmacyFilters) {
        setPharmacyLoading(true);

        try {
            const response = await axios.get('/api/v1/pharmacies', {
                params: cleanPayload(nextFilters, { includePagination: true }),
            });

            startTransition(() => {
                setPharmacies(response.data.data);
            });
        } catch (error) {
            setNotice({
                tone: 'error',
                message: extractError(error, 'Could not load the pharmacy list.'),
            });
        } finally {
            setPharmacyLoading(false);
        }
    }

    async function loadSalesEvent(nextFilters = salesFilters) {
        setSalesLoading(true);

        try {
            const response = await axios.get('/api/v1/sales', {
                params: cleanPayload(nextFilters, { includePagination: true }),
            });

            startTransition(() => {
                setSales(response.data.data);
            });
        } catch (error) {
            setNotice({
                tone: 'error',
                message: extractError(error, 'Could not load the sales list.'),
            });
        } finally {
            setSalesLoading(false);
        }
    }

    useEffect(() => {
        function syncPath() {
            setActiveTab(resolveTabFromPath(window.location.pathname));
        }

        window.addEventListener('popstate', syncPath);

        return () => {
            window.removeEventListener('popstate', syncPath);
        };
    }, []);

    useEffect(() => {
        let cancelled = false;

        async function bootstrapDashboard() {
            setDashboardLoading(true);

            try {
                const [optionsResponse, previewResponse, pharmacyResponse, salesResponse] = await Promise.all([
                    axios.get('/api/v1/reporting/options'),
                    axios.get('/api/v1/reporting/preview', {
                        params: cleanPayload(filters, { includePagination: true }),
                    }),
                    axios.get('/api/v1/pharmacies', {
                        params: cleanPayload(pharmacyFilters, { includePagination: true }),
                    }),
                    axios.get('/api/v1/sales', {
                        params: cleanPayload(salesFilters, { includePagination: true }),
                    }),
                ]);

                if (cancelled) {
                    return;
                }

                startTransition(() => {
                    setCatalog(optionsResponse.data.data);
                    setPreview(previewResponse.data.data);
                    setPharmacies(pharmacyResponse.data.data);
                    setSales(salesResponse.data.data);
                });
            } catch (error) {
                if (!cancelled) {
                    setNotice({
                        tone: 'error',
                        message: extractError(error, 'Could not load the dashboard.'),
                    });
                }
            } finally {
                if (!cancelled) {
                    setDashboardLoading(false);
                }
            }
        }

        void bootstrapDashboard();

        return () => {
            cancelled = true;
        };
    }, []);

    useEffect(() => {
        setBsRange({
            date_from_bs: toBsIso(filters.date_from),
            date_to_bs: toBsIso(filters.date_to),
        });
    }, [filters.date_from, filters.date_to]);

    function navigateToTab(tabId, options = {}) {
        const nextPath = resolvePathFromTab(tabId);

        if (!options.replace && window.location.pathname !== nextPath) {
            window.history.pushState({}, '', nextPath);
        }

        if (options.replace && window.location.pathname !== nextPath) {
            window.history.replaceState({}, '', nextPath);
        }

        setActiveTab(tabId);
    }

    useEffect(() => {
        if (
            window.location.pathname === '/'
            || !tabs.some((tab) => tab.path === window.location.pathname)
        ) {
            navigateToTab(activeTab, { replace: true });
        }
    }, []);

    useEffect(() => {
        if (!activeExportId) {
            return undefined;
        }

        let cancelled = false;

        async function pollExport() {
            try {
                const response = await axios.get(`/api/v1/reporting/exports/${activeExportId}`);

                if (cancelled) {
                    return;
                }

                const exportRecord = response.data.data;
                setActiveExport(exportRecord);

                if (exportRecord.status === 'completed' || exportRecord.status === 'failed') {
                    setActiveExportId(null);
                    await loadOptionsEvent();

                    if (exportRecord.status === 'completed') {
                        autoDownloadExport(exportRecord);
                        setNotice({
                            tone: 'success',
                            message: 'Excel file is ready. The download has started.',
                        });
                    }
                }
            } catch (error) {
                if (!cancelled) {
                    setActiveExportId(null);
                    setNotice({
                        tone: 'error',
                        message: extractError(error, 'Export polling failed.'),
                    });
                }
            }
        }

        void pollExport();
        const timer = window.setInterval(() => {
            void pollExport();
        }, 2000);

        return () => {
            cancelled = true;
            window.clearInterval(timer);
        };
    }, [activeExportId]);

    const hospitals = catalog.filters.hospitals ?? [];
    const previewPharmacies = catalog.filters.pharmacies ?? [];

    const filteredHospitals = hospitals.filter((hospital) => {
        if (!filters.tenant_id) {
            return true;
        }

        return String(hospital.tenant_id) === String(filters.tenant_id);
    });

    const filteredPreviewPharmacies = previewPharmacies.filter((pharmacy) => {
        if (!filters.hospital_id) {
            return true;
        }

        return String(pharmacy.hospital_id) === String(filters.hospital_id);
    });

    const filteredStudioHospitals = hospitals.filter((hospital) => {
        if (!pharmacyFilters.tenant_id) {
            return true;
        }

        return String(hospital.tenant_id) === String(pharmacyFilters.tenant_id);
    });

    const filteredSalesHospitals = hospitals.filter((hospital) => {
        if (!salesFilters.tenant_id) {
            return true;
        }

        return String(hospital.tenant_id) === String(salesFilters.tenant_id);
    });

    const filteredSalesPharmacies = previewPharmacies.filter((pharmacy) => {
        if (salesFilters.hospital_id) {
            return String(pharmacy.hospital_id) === String(salesFilters.hospital_id);
        }

        if (!salesFilters.tenant_id) {
            return true;
        }

        return filteredSalesHospitals.some((hospital) => String(hospital.id) === String(pharmacy.hospital_id));
    });

    const previewColumns = [
        {
            id: 'when',
            header: 'When',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{formatDateTime(row.original.sold_at)}</p>
                    <p className="mt-1 text-xs text-slate-500">{toBsLabel(row.original.sold_at)}</p>
                </div>
            ),
        },
        {
            id: 'org',
            header: 'Organization',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{row.original.tenant_name}</p>
                    <p className="mt-1 text-xs text-slate-500">{row.original.hospital_name}</p>
                </div>
            ),
        },
        {
            id: 'pharmacy',
            header: 'Pharmacy',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{row.original.pharmacy_name}</p>
                    <p className="mt-1 text-xs text-slate-500">{row.original.pharmacy_district}</p>
                </div>
            ),
        },
        {
            id: 'patient',
            header: 'Patient',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{row.original.patient_name}</p>
                    <p className="mt-1 text-xs text-slate-500">{row.original.invoice_number}</p>
                </div>
            ),
        },
        {
            id: 'medicine',
            header: 'Medicine',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{row.original.brand_name}</p>
                    <p className="mt-1 text-xs text-slate-500">
                        {row.original.generic_name} · {row.original.category_name}
                    </p>
                </div>
            ),
        },
        {
            id: 'payment',
            header: 'Payment',
            cell: ({ row }) => (
                <div className="flex flex-col gap-2">
                    <span className={`inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold capitalize ${paymentTone(row.original.payment_status)}`}>
                        {row.original.payment_status}
                    </span>
                    <span className="text-xs text-slate-500">{row.original.supplier_name}</span>
                </div>
            ),
        },
        {
            id: 'quantity',
            header: 'Qty',
            cell: ({ row }) => <span className="font-semibold text-slate-900">{formatCount(row.original.quantity)}</span>,
        },
        {
            id: 'total',
            header: 'Total',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{formatCurrency(row.original.line_total)}</p>
                    <p className="mt-1 text-xs text-slate-500">{row.original.is_cold_chain ? 'Cold-chain' : 'Standard stock'}</p>
                </div>
            ),
        },
    ];

    const pharmacyColumns = [
        {
            id: 'identity',
            header: 'Pharmacy',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{row.original.name}</p>
                    <p className="mt-1 text-xs text-slate-500">{row.original.code} · {row.original.license_number}</p>
                </div>
            ),
        },
        {
            id: 'org',
            header: 'Organization / Hospital',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{row.original.tenant_name}</p>
                    <p className="mt-1 text-xs text-slate-500">{row.original.hospital_name}</p>
                </div>
            ),
        },
        {
            id: 'location',
            header: 'Location',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{row.original.area}</p>
                    <p className="mt-1 text-xs text-slate-500">
                        {row.original.city}, {row.original.district} · {row.original.postal_code}
                    </p>
                </div>
            ),
        },
        {
            id: 'contact',
            header: 'Contact',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{row.original.contact_email}</p>
                    <p className="mt-1 text-xs text-slate-500">{row.original.email_domain}</p>
                </div>
            ),
        },
        {
            id: 'sales',
            header: 'Sales',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{formatCount(row.original.sales_count)}</p>
                    <p className="mt-1 text-xs text-slate-500">{formatDateTime(row.original.updated_at)}</p>
                </div>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            cell: ({ row }) => (
                <div className="flex flex-wrap gap-2">
                    <button
                        className="inline-flex items-center gap-2 rounded-full border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white"
                        onClick={() => {
                            openPharmacyEditModal(row.original);
                        }}
                        type="button"
                    >
                        <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                            <path d="M4 20h4l10-10-4-4L4 16v4Zm10-12 4 4" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                        Edit
                    </button>
                    <button
                        className="inline-flex items-center gap-2 rounded-full border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
                        onClick={() => {
                            setDeleteModalItem(row.original);
                        }}
                        type="button"
                    >
                        <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                            <path d="M5 7h14M9 7V5h6v2m-7 0 1 12h6l1-12" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                        Delete
                    </button>
                </div>
            ),
        },
    ];

    const salesColumns = [
        {
            id: 'sold_at',
            header: 'Sold At',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{formatDateTime(row.original.sold_at)}</p>
                    <p className="mt-1 text-xs text-slate-500">{toBsLabel(row.original.sold_at)}</p>
                </div>
            ),
        },
        {
            id: 'invoice',
            header: 'Invoice / Patient',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{row.original.invoice_number}</p>
                    <p className="mt-1 text-xs text-slate-500">{row.original.patient_name}</p>
                </div>
            ),
        },
        {
            id: 'org',
            header: 'Organization / Pharmacy',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{row.original.tenant_name}</p>
                    <p className="mt-1 text-xs text-slate-500">{row.original.hospital_name} · {row.original.pharmacy_name}</p>
                </div>
            ),
        },
        {
            id: 'medicine',
            header: 'Medicine',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{row.original.brand_name}</p>
                    <p className="mt-1 text-xs text-slate-500">{row.original.generic_name} · Batch {row.original.batch_number}</p>
                </div>
            ),
        },
        {
            id: 'payment',
            header: 'Payment',
            cell: ({ row }) => (
                <div className="flex flex-col gap-2">
                    <span className={`inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold capitalize ${paymentTone(row.original.payment_status)}`}>
                        {row.original.payment_status}
                    </span>
                    <span className="text-xs text-slate-500">{row.original.payment_method.replaceAll('-', ' ')}</span>
                </div>
            ),
        },
        {
            id: 'amounts',
            header: 'Amounts',
            cell: ({ row }) => (
                <div>
                    <p className="font-semibold text-slate-900">{formatCurrency(row.original.line_total)}</p>
                    <p className="mt-1 text-xs text-slate-500">
                        Qty {formatCount(row.original.quantity)} · NPR {row.original.unit_price}
                    </p>
                </div>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            cell: ({ row }) => (
                <div className="flex flex-wrap gap-2">
                    <button
                        className="inline-flex items-center gap-2 rounded-full border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white"
                        onClick={() => {
                            openSaleEditModal(row.original);
                        }}
                        type="button"
                    >
                        <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                            <path d="M4 20h4l10-10-4-4L4 16v4Zm10-12 4 4" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                        Edit
                    </button>
                    <button
                        className="inline-flex items-center gap-2 rounded-full border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
                        onClick={() => {
                            setDeleteSaleModalItem(row.original);
                        }}
                        type="button"
                    >
                        <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                            <path d="M5 7h14M9 7V5h6v2m-7 0 1 12h6l1-12" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                        Delete
                    </button>
                </div>
            ),
        },
    ];

    function openPharmacyCreateModal() {
        setEditingPharmacyId(null);
        setPharmacyForm(initialPharmacyForm());
        setPharmacyModalOpen(true);
    }

    function openPharmacyEditModal(pharmacy) {
        setEditingPharmacyId(pharmacy.id);
        setPharmacyForm({
            hospital_id: String(pharmacy.hospital_id),
            code: pharmacy.code,
            name: pharmacy.name,
            license_number: pharmacy.license_number,
            contact_email: pharmacy.contact_email,
            area: pharmacy.area,
            city: pharmacy.city,
            district: pharmacy.district,
            province: pharmacy.province,
            postal_code: pharmacy.postal_code,
            email_domain: pharmacy.email_domain,
            seed_demo_sale: false,
        });
        setPharmacyModalOpen(true);
        navigateToTab('pharmacies');
    }

    function openSaleCreateModal() {
        setEditingSaleItemId(null);
        setSaleForm(initialSaleForm());
        setSaleModalOpen(true);
    }

    function openSaleEditModal(sale) {
        setEditingSaleItemId(sale.sale_item_id);
        setSaleForm({
            pharmacy_id: String(sale.pharmacy_id),
            medicine_id: String(sale.medicine_id),
            invoice_number: sale.invoice_number,
            payment_method: sale.payment_method,
            payment_status: sale.payment_status,
            sold_at: formatDateTimeForInput(sale.sold_at),
            batch_number: sale.batch_number,
            quantity: String(sale.quantity),
            unit_price: sale.unit_price,
            discount_amount: sale.discount_amount,
            tax_amount: sale.tax_amount,
            expires_at: sale.expires_at ?? '',
        });
        setSaleModalOpen(true);
        navigateToTab('sales');
    }

    async function handleDeletePharmacy(pharmacyId) {
        try {
            await axios.delete(`/api/v1/pharmacies/${pharmacyId}`);
            setNotice({
                tone: 'success',
                message: 'Pharmacy deleted successfully.',
            });
            setDeleteModalItem(null);
            await loadPharmaciesEvent(pharmacyFilters);
            await loadOptionsEvent();
        } catch (error) {
            setNotice({
                tone: 'error',
                message: extractError(error, 'Could not delete that pharmacy.'),
            });
        }
    }

    function handleFilterChange(event) {
        const { name, value } = event.target;

        setFilters((current) => {
            const next = {
                ...current,
                [name]: value,
                page: 1,
            };

            if (name === 'tenant_id') {
                next.hospital_id = '';
                next.pharmacy_id = '';
            }

            if (name === 'hospital_id') {
                next.pharmacy_id = '';
            }

            return next;
        });
    }

    function handleBsRangeChange(name, value) {
        if (!name) {
            return;
        }

        setBsRange((current) => ({
            ...current,
            [name]: value,
        }));

        const adDate = toAdIso(value);

        if (!adDate) {
            return;
        }

        setFilters((current) => ({
            ...current,
            [name === 'date_from_bs' ? 'date_from' : 'date_to']: adDate,
            page: 1,
        }));
    }

async function downloadFile(url, fallbackName, successMessage, options = {}) {
    setSubmittingExport(true);

    try {
        const response = await axios.get(url, {
            responseType: 'blob',
            });
            const blob = new Blob([response.data], {
                type: response.headers['content-type'] ?? 'application/octet-stream',
            });
            const link = document.createElement('a');
            const objectUrl = window.URL.createObjectURL(blob);
            const requestedFormat = options.requestedFormat ?? null;
            const actualFormat = response.headers['x-pharamapoc-actual-format'] ?? null;
            const didFallbackToCsv = requestedFormat === 'xlsx' && actualFormat === 'csv';
            link.href = objectUrl;
            link.download = extractFilename(response.headers['content-disposition'], fallbackName);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(objectUrl);
            setNotice({
                tone: 'success',
                message: didFallbackToCsv
                    ? 'The range was too large for a real XLSX, so I switched to fast Excel CSV.'
                    : successMessage,
            });
        } catch (error) {
            setNotice({
                tone: 'error',
                message: await extractDownloadError(error, 'Could not download the file.'),
            });
        } finally {
            setSubmittingExport(false);
        }
    }

    async function requestExport(format) {
        setSubmittingExport(true);
        setNotice(null);

        try {
            const response = await axios.post(
                '/api/v1/reporting/exports',
                cleanPayload(
                    {
                        ...filters,
                        format,
                    },
                    { includeFormat: true }
                )
            );
            const exportRecord = response.data.data;

            setActiveExport(exportRecord);

            if (exportRecord.status === 'completed') {
                setActiveExportId(null);
                autoDownloadExport(exportRecord);
                setNotice({
                    tone: 'success',
                    message: 'A matching Excel file was already ready, so the download has started.',
                });
            } else {
                setActiveExportId(exportRecord.id);
                setNotice({
                    tone: 'info',
                    message: 'Excel export started in the background. You can keep working while it prepares.',
                });
            }

            await loadOptionsEvent();
        } catch (error) {
            setNotice({
                tone: 'error',
                message: extractError(error, 'Could not create the export.'),
            });
        } finally {
            setSubmittingExport(false);
        }
    }

    function handleSalesFilterChange(event) {
        const { name, value } = event.target;

        setSalesFilters((current) => {
            const next = {
                ...current,
                [name]: value,
                page: 1,
            };

            if (name === 'tenant_id') {
                next.hospital_id = '';
                next.pharmacy_id = '';
            }

            if (name === 'hospital_id') {
                next.pharmacy_id = '';
            }

            return next;
        });
    }

    function handlePharmacyFilterChange(event) {
        const { name, value } = event.target;

        setPharmacyFilters((current) => {
            const next = {
                ...current,
                [name]: value,
                page: 1,
            };

            if (name === 'tenant_id') {
                next.hospital_id = '';
            }

            return next;
        });
    }

    function handlePharmacyFormChange(event) {
        const { name, type, checked, value } = event.target;

        setPharmacyForm((current) => ({
            ...current,
            [name]: type === 'checkbox' ? checked : value,
        }));
    }

    function handleSaleFormChange(event) {
        const { name, value } = event.target;

        setSaleForm((current) => {
            const next = {
                ...current,
                [name]: value,
            };

            if (name === 'medicine_id') {
                const medicine = (catalog.filters.medicines ?? []).find((item) => String(item.id) === String(value));

                if (medicine && !editingSaleItemId) {
                    next.unit_price = String(medicine.unit_price ?? '');
                }
            }

            return next;
        });
    }

    async function requestPharmacyDownload(format) {
        const params = new URLSearchParams(cleanPayload({
            ...pharmacyFilters,
            format,
        }, { includeFormat: true }));

        await downloadFile(
            `/api/v1/pharmacies/export?${params.toString()}`,
            `pharamaPOC-pharmacies.${format}`,
            'Pharmacy Excel file is ready.',
            { requestedFormat: format }
        );
    }

    async function requestSalesDownload(format) {
        const params = new URLSearchParams(cleanPayload({
            ...salesFilters,
            format,
        }, { includeFormat: true }));

        await downloadFile(
            `/api/v1/sales/export?${params.toString()}`,
            `pharamaPOC-sales.${format}`,
            'Sales Excel file is ready.',
            { requestedFormat: format }
        );
    }

    async function requestSalesTemplate() {
        await downloadFile(
            '/api/v1/sales/template',
            'pharamaPOC-sales-template.csv',
            'Sample sales format downloaded.'
        );
    }

    async function handlePharmacySubmit(event) {
        event.preventDefault();
        setPharmacySubmitting(true);

        try {
            const payload = cleanPayload(pharmacyForm);
            const response = editingPharmacyId
                ? await axios.put(`/api/v1/pharmacies/${editingPharmacyId}`, payload)
                : await axios.post('/api/v1/pharmacies', payload);

            setNotice({
                tone: 'success',
                message: editingPharmacyId
                    ? 'Pharmacy updated successfully. Any new demo sale is already available in preview and export.'
                    : 'Pharmacy created successfully. Any demo sale is already available in preview and export.',
            });
            setEditingPharmacyId(null);
            setPharmacyForm(initialPharmacyForm());

            await Promise.all([
                loadPharmaciesEvent(pharmacyFilters),
                loadOptionsEvent(),
                loadPreviewEvent({
                    ...filters,
                    page: 1,
                }),
            ]);

            if (response?.data?.data) {
                setPharmacyModalOpen(false);
                navigateToTab('pharmacies');
            }
        } catch (error) {
            setNotice({
                tone: 'error',
                message: extractError(error, 'Could not save the pharmacy.'),
            });
        } finally {
            setPharmacySubmitting(false);
        }
    }

    async function handleSaleSubmit(event) {
        event.preventDefault();
        setSalesSubmitting(true);

        try {
            const payload = cleanPayload(saleForm);
            const response = editingSaleItemId
                ? await axios.put(`/api/v1/sales/${editingSaleItemId}`, payload)
                : await axios.post('/api/v1/sales', payload);

            setNotice({
                tone: 'success',
                message: editingSaleItemId
                    ? 'Sale updated successfully. The report preview now uses the fresh row immediately.'
                    : 'Sale created successfully. The new row is already ready for preview and export.',
            });

            await Promise.all([
                loadSalesEvent(salesFilters),
                loadPreviewEvent({
                    ...filters,
                    page: 1,
                }),
                loadOptionsEvent(),
            ]);

            if (response?.data?.data) {
                setSaleModalOpen(false);
                setEditingSaleItemId(null);
                setSaleForm(initialSaleForm());
                navigateToTab('sales');
            }
        } catch (error) {
            setNotice({
                tone: 'error',
                message: extractError(error, 'Could not save the sale.'),
            });
        } finally {
            setSalesSubmitting(false);
        }
    }

    async function handleDeleteSale(saleItemId) {
        try {
            await axios.delete(`/api/v1/sales/${saleItemId}`);
            setDeleteSaleModalItem(null);
            setNotice({
                tone: 'success',
                message: 'Sale deleted successfully. The report preview now hides it right away.',
            });

            await Promise.all([
                loadSalesEvent(salesFilters),
                loadPreviewEvent({
                    ...filters,
                    page: 1,
                }),
                loadOptionsEvent(),
            ]);
        } catch (error) {
            setNotice({
                tone: 'error',
                message: extractError(error, 'Could not delete that sale.'),
            });
        }
    }

    function resetPharmacyForm() {
        setEditingPharmacyId(null);
        setPharmacyForm(initialPharmacyForm());
        setPharmacyModalOpen(false);
    }

    function resetSaleForm() {
        setEditingSaleItemId(null);
        setSaleForm(initialSaleForm());
        setSaleModalOpen(false);
    }

    const organizationName = currentUser?.organization?.name ?? 'All organizations';
    const hospitalName = currentUser?.hospital?.name ?? 'Every hospital';
    const scopeSummary = currentUser?.hospital
        ? `${organizationName} / ${hospitalName}`
        : currentUser?.organization
          ? organizationName
          : 'Platform-wide reporting access';
    const accessLabel = currentUser?.hospital ? 'Hospital Access' : currentUser?.organization ? 'Organization Access' : 'Platform Access';
    const pageMeta = {
        dashboard: {
            breadcrumb: 'Dashboard',
            title: 'Dashboard',
            description: 'View key totals, scope details, and recent exports.',
        },
        overview: {
            breadcrumb: 'Reports',
            title: 'Sales Report',
            description: 'Pick your range, check the rows, and download a fast file for Excel.',
        },
        sales: {
            breadcrumb: 'Sales',
            title: 'Sales Management',
            description: 'Create, edit, delete, search, and export sales from one page.',
        },
        pharmacies: {
            breadcrumb: 'Pharmacies',
            title: 'Pharmacy Management',
            description: 'Create, edit, and review pharmacy records.',
        },
        exports: {
            breadcrumb: 'Exports',
            title: 'Export Files',
            description: 'Track running jobs and download finished files.',
        },
    };
    const currentPage = pageMeta[activeTab] ?? pageMeta.overview;
    const dashboardMetrics = [
        { label: 'Organizations', value: catalog.stats.tenants, helper: 'Visible in this login.' },
        { label: 'Hospitals', value: catalog.stats.hospitals, helper: 'Linked to the current scope.' },
        { label: 'Pharmacies', value: catalog.stats.pharmacies, helper: 'Ready for report and CRUD.' },
        { label: 'Sale Rows', value: catalog.stats.sale_items, helper: 'Used in preview and export.' },
    ];
    const maxMetricValue = Math.max(...dashboardMetrics.map((item) => Number(item.value ?? 0)), 1);
    const exportStatusCounts = catalog.recent_exports.reduce((carry, item) => {
        const key = item.status ?? 'queued';
        carry[key] = (carry[key] ?? 0) + 1;

        return carry;
    }, {});
    const exportTotal = Math.max(1, Object.values(exportStatusCounts).reduce((sum, value) => sum + Number(value), 0));
    const completedPct = Math.round(((exportStatusCounts.completed ?? 0) / exportTotal) * 100);
    const processingPct = completedPct + Math.round(((exportStatusCounts.processing ?? 0) / exportTotal) * 100);
    const failedPct = processingPct + Math.round(((exportStatusCounts.failed ?? 0) / exportTotal) * 100);
    const exportPieStyle = {
        background: `conic-gradient(#1d4ed8 0% ${completedPct}%, #14b8a6 ${completedPct}% ${processingPct}%, #f59e0b ${processingPct}% ${failedPct}%, #e2e8f0 ${failedPct}% 100%)`,
    };

    return (
        <div className="workspace-shell">
            <aside className="workspace-sidebar">
                <div className="rounded-[2rem] bg-[#21345b] p-5 text-white shadow-[0_28px_80px_rgba(15,23,42,0.18)]">
                    <div className="flex items-center gap-4">
                        <div className="flex h-14 w-14 items-center justify-center rounded-[1.5rem] bg-white/10">
                            <svg className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                                <path d="M4 12h16" strokeLinecap="round" strokeLinejoin="round" />
                                <path d="M12 4v16" strokeLinecap="round" strokeLinejoin="round" />
                                <path d="M7 7l10 10" strokeLinecap="round" strokeLinejoin="round" opacity=".35" />
                            </svg>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200">pharamaPOC</p>
                            <h1 className="mt-1 text-2xl font-semibold">Admin Panel</h1>
                        </div>
                    </div>

                    <div className="mt-6 rounded-[1.6rem] bg-white/10 px-5 py-5">
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200">Signed In</p>
                        <p className="mt-3 text-lg font-semibold">{currentUser?.name ?? 'User'}</p>
                        <p className="mt-1 text-sm text-slate-200">{roleLabel(currentUser?.role)}</p>
                        <p className="mt-4 text-sm leading-6 text-slate-200">{scopeSummary}</p>
                    </div>

                    <div className="mt-6">
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200">Navigation</p>
                        <div className="mt-3 flex flex-col gap-2">
                            {tabs.map((tab) => (
                                <TabButton
                                    active={activeTab === tab.id}
                                    key={tab.id}
                                    onClick={() => {
                                        navigateToTab(tab.id);
                                    }}
                                >
                                    {tab.label}
                                </TabButton>
                            ))}
                        </div>
                    </div>

                    <div className="mt-6 flex flex-wrap gap-3">
                        <button
                            className="inline-flex items-center rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition hover:border-white/30 hover:bg-white/10"
                            onClick={() => {
                                void onLogout?.();
                            }}
                            type="button"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </aside>

            <main className="workspace-main">
                <section className="glass-panel p-6">
                    <div className="flex flex-wrap items-start justify-between gap-5">
                        <div>
                            <p className="text-sm font-semibold text-slate-500">Home / {currentPage.breadcrumb}</p>
                            <h2 className="mt-3 font-display text-3xl font-semibold text-slate-950">{currentPage.title}</h2>
                            <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">{currentPage.description}</p>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="data-pill">
                                <span className="data-pill-label">Role</span>
                                <span className="data-pill-value">{roleLabel(currentUser?.role)}</span>
                            </div>
                            <div className="data-pill">
                                <span className="data-pill-label">Access</span>
                                <span className="data-pill-value">{accessLabel}</span>
                            </div>
                            <div className="data-pill">
                                <span className="data-pill-label">Latest Sale</span>
                                <span className="data-pill-value">{formatDateTime(catalog.stats.latest_sale_at)}</span>
                            </div>
                        </div>
                    </div>

                </section>

                {notice ? (
                    <div className={`mt-6 rounded-3xl border px-5 py-4 text-sm font-medium ${noticeTone(notice.tone)}`}>
                        {notice.message}
                    </div>
                ) : null}

            {activeTab === 'dashboard' ? (
                <section className="mt-6 space-y-6">
                    <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
                        {dashboardMetrics.map((item) => (
                            <MetricTile
                                helper={item.helper}
                                key={item.label}
                                label={item.label}
                                value={formatCount(item.value)}
                            />
                        ))}
                    </div>

                    <div className="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                        <div className="glass-panel p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="eyebrow">System View</p>
                                    <h2 className="mt-2 font-display text-2xl font-semibold text-slate-950">Scale Snapshot</h2>
                                </div>
                            </div>

                            <div className="mt-6 space-y-4">
                                {dashboardMetrics.map((item, index) => (
                                    <div key={item.label}>
                                        <div className="flex items-center justify-between gap-4 text-sm font-semibold text-slate-700">
                                            <span>{item.label}</span>
                                            <span>{formatCount(item.value)}</span>
                                        </div>
                                        <div className="mt-2 h-3 rounded-full bg-slate-100">
                                            <div
                                                className={`h-3 rounded-full ${['bg-sky-500', 'bg-indigo-500', 'bg-teal-500', 'bg-amber-500'][index]}`}
                                                style={{ width: `${Math.max(8, Math.round((Number(item.value ?? 0) / maxMetricValue) * 100))}%` }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="glass-panel p-6">
                            <p className="eyebrow">Export Status</p>
                            <h2 className="mt-2 font-display text-2xl font-semibold text-slate-950">Recent Export Mix</h2>

                            <div className="mt-6 flex flex-col items-center gap-6 sm:flex-row">
                                <div className="flex h-48 w-48 items-center justify-center rounded-full" style={exportPieStyle}>
                                    <div className="flex h-28 w-28 items-center justify-center rounded-full bg-white text-center shadow-inner">
                                        <div>
                                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Exports</p>
                                            <p className="mt-2 text-3xl font-semibold text-slate-950">{formatCount(exportTotal)}</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="w-full space-y-3">
                                    {[
                                        ['Completed', exportStatusCounts.completed ?? 0, 'bg-sky-500'],
                                        ['Processing', exportStatusCounts.processing ?? 0, 'bg-teal-500'],
                                        ['Failed', exportStatusCounts.failed ?? 0, 'bg-amber-500'],
                                    ].map(([label, value, tone]) => (
                                        <div className="flex items-center justify-between gap-3" key={label}>
                                            <div className="flex items-center gap-3">
                                                <span className={`h-3 w-3 rounded-full ${tone}`} />
                                                <span className="text-sm font-medium text-slate-700">{label}</span>
                                            </div>
                                            <span className="text-sm font-semibold text-slate-900">{formatCount(value)}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
                        <div className="glass-panel p-6">
                            <p className="eyebrow">Current Access</p>
                            <h2 className="mt-2 font-display text-2xl font-semibold text-slate-950">Account Details</h2>

                            <div className="mt-5 grid gap-4 sm:grid-cols-2">
                                <div className="data-pill">
                                    <span className="data-pill-label">Organization</span>
                                    <span className="data-pill-value">{organizationName}</span>
                                </div>
                                <div className="data-pill">
                                    <span className="data-pill-label">Hospital</span>
                                    <span className="data-pill-value">{currentUser?.hospital?.name ?? 'All hospitals'}</span>
                                </div>
                                <div className="data-pill">
                                    <span className="data-pill-label">Access</span>
                                    <span className="data-pill-value">{accessLabel}</span>
                                </div>
                                <div className="data-pill">
                                    <span className="data-pill-label">Latest Sale</span>
                                    <span className="data-pill-value">{formatDateTime(catalog.stats.latest_sale_at)}</span>
                                </div>
                            </div>

                            <div className="mt-6 flex flex-wrap gap-3">
                                <button
                                    className="inline-flex items-center gap-2 rounded-full bg-[#21345b] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#172741]"
                                    onClick={() => {
                                        navigateToTab('overview');
                                    }}
                                    type="button"
                                >
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path d="M12 5v14M5 12h14" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                    Open Reports
                                </button>
                                <button
                                    className="inline-flex items-center gap-2 rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white"
                                    onClick={() => {
                                        navigateToTab('exports');
                                    }}
                                    type="button"
                                >
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path d="M12 4v10m0 0 4-4m-4 4-4-4M5 19h14" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                    Open Exports
                                </button>
                            </div>
                        </div>

                        <div className="glass-panel p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="eyebrow">Recent Files</p>
                                    <h2 className="mt-2 font-display text-2xl font-semibold text-slate-950">Latest Exports</h2>
                                </div>
                            </div>

                            <div className="mt-5 space-y-4">
                                {catalog.recent_exports.slice(0, 3).map((item) => (
                                    <ExportCard item={item} key={item.id} />
                                ))}

                                {!catalog.recent_exports.length && !dashboardLoading ? (
                                    <div className="rounded-3xl border border-dashed border-slate-300 bg-white/50 px-5 py-8 text-center text-sm text-slate-500">
                                        No export files yet.
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </section>
            ) : null}

            {activeTab === 'overview' ? (
                <section className="mt-6 space-y-6">
                    <form className="glass-panel p-6" onSubmit={(event) => {
                        event.preventDefault();
                    }}>
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="eyebrow">Report Filters</p>
                                    <h2 className="mt-2 font-display text-2xl font-semibold text-slate-950">Sales Preview</h2>
                                    <p className="mt-2 text-sm text-slate-500">
                                        Pick the date range and filters you want, then preview the rows or create an export file.
                                    </p>
                                </div>
                                <div className="rounded-full bg-slate-100 px-4 py-2 text-sm font-medium text-slate-600">
                                    Excel export ready
                                </div>
                            </div>

                            <div className="mt-6 rounded-3xl border border-slate-200/80 bg-slate-50/80 p-5">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <label className="field-shell">
                                        <span className="field-label">Date From (AD)</span>
                                        <input className="field-input" name="date_from" onChange={handleFilterChange} type="date" value={filters.date_from} />
                                    </label>
                                    <label className="field-shell">
                                        <span className="field-label">Date To (AD)</span>
                                        <input className="field-input" name="date_to" onChange={handleFilterChange} type="date" value={filters.date_to} />
                                    </label>
                                    <label className="field-shell">
                                        <span className="field-label">Date From (BS)</span>
                                        <NepaliDatePicker
                                            className="w-full"
                                            inputClassName="field-input field-input-nepali"
                                            onChange={(value) => {
                                                handleBsRangeChange('date_from_bs', value);
                                            }}
                                            options={{ calenderLocale: 'ne', valueLocale: 'en' }}
                                            value={bsRange.date_from_bs}
                                        />
                                    </label>
                                    <label className="field-shell">
                                        <span className="field-label">Date To (BS)</span>
                                        <NepaliDatePicker
                                            className="w-full"
                                            inputClassName="field-input field-input-nepali"
                                            onChange={(value) => {
                                                handleBsRangeChange('date_to_bs', value);
                                            }}
                                            options={{ calenderLocale: 'ne', valueLocale: 'en' }}
                                            value={bsRange.date_to_bs}
                                        />
                                    </label>
                                </div>

                                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div className="rounded-2xl bg-white px-4 py-4">
                                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">BS Label</p>
                                        <p className="mt-2 text-sm font-semibold text-slate-900">{toBsLabel(filters.date_from)}</p>
                                        <p className="mt-1 text-sm font-semibold text-slate-900">{toBsLabel(filters.date_to)}</p>
                                    </div>
                                    <div className="rounded-2xl bg-white px-4 py-4">
                                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Nepali Time</p>
                                        <p className="mt-2 text-sm font-semibold text-slate-900">{formatNepaliDateTime(`${filters.date_from}T00:00:00+05:45`)}</p>
                                        <p className="mt-1 text-sm font-semibold text-slate-900">{formatNepaliDateTime(`${filters.date_to}T23:59:00+05:45`)}</p>
                                    </div>
                                </div>
                            </div>

                            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <label className="field-shell">
                                    <span className="field-label">Organization</span>
                                    <select className="field-input" name="tenant_id" onChange={handleFilterChange} value={filters.tenant_id}>
                                        <option value="">All organizations</option>
                                        {catalog.filters.tenants.map((tenant) => (
                                            <option key={tenant.id} value={tenant.id}>
                                                {tenant.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <label className="field-shell">
                                    <span className="field-label">Hospital</span>
                                    <select className="field-input" name="hospital_id" onChange={handleFilterChange} value={filters.hospital_id}>
                                        <option value="">All hospitals</option>
                                        {filteredHospitals.map((hospital) => (
                                            <option key={hospital.id} value={hospital.id}>
                                                {hospital.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <label className="field-shell">
                                    <span className="field-label">Pharmacy</span>
                                    <select className="field-input" name="pharmacy_id" onChange={handleFilterChange} value={filters.pharmacy_id}>
                                        <option value="">All pharmacies</option>
                                        {filteredPreviewPharmacies.map((pharmacy) => (
                                            <option key={pharmacy.id} value={pharmacy.id}>
                                                {pharmacy.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <label className="field-shell">
                                    <span className="field-label">Category</span>
                                    <select className="field-input" name="category_id" onChange={handleFilterChange} value={filters.category_id}>
                                        <option value="">All categories</option>
                                        {catalog.filters.categories.map((category) => (
                                            <option key={category.id} value={category.id}>
                                                {category.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <label className="field-shell">
                                    <span className="field-label">Supplier</span>
                                    <select className="field-input" name="supplier_id" onChange={handleFilterChange} value={filters.supplier_id}>
                                        <option value="">All suppliers</option>
                                        {catalog.filters.suppliers.map((supplier) => (
                                            <option key={supplier.id} value={supplier.id}>
                                                {supplier.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <label className="field-shell">
                                    <span className="field-label">Payment Status</span>
                                    <select className="field-input" name="payment_status" onChange={handleFilterChange} value={filters.payment_status}>
                                        <option value="">All statuses</option>
                                        {catalog.filters.payment_statuses.map((status) => (
                                            <option key={status.value} value={status.value}>
                                                {status.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <label className="field-shell">
                                    <span className="field-label">Cold Chain</span>
                                    <select className="field-input" name="cold_chain" onChange={handleFilterChange} value={filters.cold_chain}>
                                        <option value="">All stock</option>
                                        <option value="1">Cold-chain only</option>
                                        <option value="0">Non cold-chain only</option>
                                    </select>
                                </label>

                            </div>

                            <div className="mt-6 flex flex-wrap gap-3">
                                <button
                                    className="inline-flex items-center gap-2 rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white"
                                    disabled={previewLoading}
                                    onClick={() => {
                                        void loadPreviewEvent(filters);
                                    }}
                                    type="button"
                                >
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path d="M4 12h16M12 4v16" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                    {previewLoading ? 'Loading Preview...' : 'Load Preview'}
                                </button>
                                <button
                                    className="inline-flex items-center gap-2 rounded-full bg-[#21345b] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#172741] disabled:cursor-not-allowed disabled:bg-slate-400"
                                    disabled={submittingExport}
                                    onClick={() => {
                                        void requestExport('xlsx');
                                    }}
                                    type="button"
                                >
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path d="M7 4h7l5 5v11H7z" strokeLinecap="round" strokeLinejoin="round" />
                                        <path d="M14 4v5h5" strokeLinecap="round" strokeLinejoin="round" />
                                        <path d="M9 16l2-3 2 3 2-3" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                    {submittingExport ? 'Preparing Excel...' : 'Excel Download'}
                                </button>
                            </div>

                            {activeExport && ['pending', 'processing'].includes(activeExport.status) ? (
                                <div className="mt-6 rounded-[28px] border border-sky-200 bg-sky-50/80 p-5">
                                    <div className="flex flex-wrap items-start justify-between gap-4">
                                        <div>
                                            <p className="text-sm font-semibold text-slate-900">Excel export is running</p>
                                            <p className="mt-1 text-sm text-slate-600">
                                                {exportPhaseLabel(activeExport)}. You can keep using the app while the workbook is prepared.
                                            </p>
                                        </div>
                                        <button
                                            className="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-white px-4 py-2 text-sm font-semibold text-sky-700 transition hover:border-sky-300 hover:bg-sky-100"
                                            onClick={() => {
                                                navigateToTab('exports');
                                            }}
                                            type="button"
                                        >
                                            Open Exports
                                        </button>
                                    </div>

                                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
                                        <span>{activeExport.progress}% complete</span>
                                        <span>
                                            {activeExport.requested_rows
                                                ? `${formatCount(activeExport.requested_rows)} rows in this file`
                                                : 'Counting rows'}
                                        </span>
                                    </div>

                                    <div className="mt-2 h-2 rounded-full bg-sky-100">
                                        <div
                                            className="h-2 rounded-full bg-gradient-to-r from-sky-500 to-cyan-500 transition-all duration-500"
                                            style={{ width: `${activeExport.progress}%` }}
                                        />
                                    </div>
                                </div>
                            ) : null}
                        </form>

                        <div className="glass-panel overflow-hidden p-6">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="eyebrow">Preview Table</p>
                                    <h2 className="mt-2 font-display text-2xl font-semibold text-slate-950">Sales Rows</h2>
                                    <p className="mt-2 text-sm text-slate-500">
                                        This table loads page by page so the browser stays light even when the data is large.
                                    </p>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-3">
                                    <div className="data-pill">
                                        <span className="data-pill-label">Rows</span>
                                        <span className="data-pill-value">{formatCount(preview.summary.total_rows)}</span>
                                    </div>
                                    <div className="data-pill">
                                        <span className="data-pill-label">Units</span>
                                        <span className="data-pill-value">{formatCount(preview.summary.total_units)}</span>
                                    </div>
                                    <div className="data-pill">
                                        <span className="data-pill-label">Revenue</span>
                                        <span className="data-pill-value">{formatCurrency(preview.summary.total_revenue)}</span>
                                    </div>
                                </div>
                            </div>

                            <div className="mt-5">
                                <DataTable
                                    columns={previewColumns}
                                    data={preview.rows}
                                    emptyMessage="No rows matched the current filter set."
                                    loading={previewLoading || dashboardLoading}
                                    onPageChange={async (page) => {
                                        const nextFilters = {
                                            ...filters,
                                            page,
                                        };

                                        setFilters(nextFilters);
                                        await loadPreviewEvent(nextFilters);
                                    }}
                                    onPerPageChange={async (perPage) => {
                                        const nextFilters = {
                                            ...filters,
                                            page: 1,
                                            per_page: perPage,
                                        };

                                        setFilters(nextFilters);
                                        await loadPreviewEvent(nextFilters);
                                    }}
                                    pagination={preview.pagination}
                                />
                            </div>
                        </div>
                </section>
            ) : null}

            {activeTab === 'sales' ? (
                <section className="mt-6">
                    <div className="glass-panel p-6">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p className="eyebrow">Sales List</p>
                                <h2 className="mt-2 font-display text-2xl font-semibold text-slate-950">Add and Manage Sales</h2>
                            </div>
                            <div className="flex flex-wrap gap-3">
                                <div className="rounded-full bg-slate-100 px-4 py-2 text-sm font-medium text-slate-600">
                                    {formatCount(sales.pagination.total)} records
                                </div>
                                <button
                                    className="inline-flex items-center gap-2 rounded-full bg-[#21345b] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#172741]"
                                    onClick={openSaleCreateModal}
                                    type="button"
                                >
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path d="M12 5v14M5 12h14" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                    Add Sale
                                </button>
                            </div>
                        </div>

                        <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <label className="field-shell">
                                <span className="field-label">Date From</span>
                                <input className="field-input" name="date_from" onChange={handleSalesFilterChange} type="date" value={salesFilters.date_from} />
                            </label>
                            <label className="field-shell">
                                <span className="field-label">Date To</span>
                                <input className="field-input" name="date_to" onChange={handleSalesFilterChange} type="date" value={salesFilters.date_to} />
                            </label>
                            <label className="field-shell xl:col-span-2">
                                <span className="field-label">Search</span>
                                <input
                                    className="field-input"
                                    name="search"
                                    onChange={handleSalesFilterChange}
                                    placeholder="Invoice, patient, pharmacy, or medicine"
                                    value={salesFilters.search}
                                />
                            </label>
                        </div>

                        <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                            <label className="field-shell">
                                <span className="field-label">Organization</span>
                                <select className="field-input" name="tenant_id" onChange={handleSalesFilterChange} value={salesFilters.tenant_id}>
                                    <option value="">All organizations</option>
                                    {catalog.filters.tenants.map((tenant) => (
                                        <option key={tenant.id} value={tenant.id}>
                                            {tenant.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="field-shell">
                                <span className="field-label">Hospital</span>
                                <select className="field-input" name="hospital_id" onChange={handleSalesFilterChange} value={salesFilters.hospital_id}>
                                    <option value="">All hospitals</option>
                                    {filteredSalesHospitals.map((hospital) => (
                                        <option key={hospital.id} value={hospital.id}>
                                            {hospital.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="field-shell">
                                <span className="field-label">Pharmacy</span>
                                <select className="field-input" name="pharmacy_id" onChange={handleSalesFilterChange} value={salesFilters.pharmacy_id}>
                                    <option value="">All pharmacies</option>
                                    {filteredSalesPharmacies.map((pharmacy) => (
                                        <option key={pharmacy.id} value={pharmacy.id}>
                                            {pharmacy.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="field-shell">
                                <span className="field-label">Medicine</span>
                                <select className="field-input" name="medicine_id" onChange={handleSalesFilterChange} value={salesFilters.medicine_id}>
                                    <option value="">All medicines</option>
                                    {catalog.filters.medicines.map((medicine) => (
                                        <option key={medicine.id} value={medicine.id}>
                                            {medicine.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="field-shell">
                                <span className="field-label">Payment</span>
                                <select className="field-input" name="payment_status" onChange={handleSalesFilterChange} value={salesFilters.payment_status}>
                                    <option value="">All statuses</option>
                                    {catalog.filters.payment_statuses.map((status) => (
                                        <option key={status.value} value={status.value}>
                                            {status.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>

                        <div className="mt-4 flex flex-wrap gap-3">
                            <button
                                className="inline-flex items-center gap-2 rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white"
                                onClick={() => {
                                    void loadSalesEvent(salesFilters);
                                }}
                                type="button"
                            >
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                    <path d="M4 12h16M12 4v16" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                                Load Sales
                            </button>
                            <button
                                className="inline-flex items-center gap-2 rounded-full bg-[#21345b] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#172741] disabled:cursor-not-allowed disabled:bg-slate-400"
                                disabled={submittingExport}
                                onClick={() => {
                                    void requestSalesDownload('xlsx');
                                }}
                                type="button"
                            >
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                    <path d="M7 4h7l5 5v11H7z" strokeLinecap="round" strokeLinejoin="round" />
                                    <path d="M14 4v5h5" strokeLinecap="round" strokeLinejoin="round" />
                                    <path d="M9 16l2-3 2 3 2-3" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                                Excel Download
                            </button>
                            <button
                                className="inline-flex items-center gap-2 rounded-full border border-transparent px-5 py-3 text-sm font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                                disabled={submittingExport}
                                onClick={() => {
                                    void requestSalesTemplate();
                                }}
                                type="button"
                            >
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                    <path d="M7 4h7l5 5v11H7z" strokeLinecap="round" strokeLinejoin="round" />
                                    <path d="M14 4v5h5" strokeLinecap="round" strokeLinejoin="round" />
                                    <path d="M9 14h6M9 18h6" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                                Sample Format
                            </button>
                        </div>

                        <div className="mt-5 grid gap-3 sm:grid-cols-4">
                            <div className="data-pill">
                                <span className="data-pill-label">Rows</span>
                                <span className="data-pill-value">{formatCount(sales.summary.total_rows)}</span>
                            </div>
                            <div className="data-pill">
                                <span className="data-pill-label">Units</span>
                                <span className="data-pill-value">{formatCount(sales.summary.total_quantity)}</span>
                            </div>
                            <div className="data-pill">
                                <span className="data-pill-label">Revenue</span>
                                <span className="data-pill-value">{formatCurrency(sales.summary.total_revenue)}</span>
                            </div>
                            <div className="data-pill">
                                <span className="data-pill-label">Latest Sale</span>
                                <span className="data-pill-value">{formatDateTime(sales.summary.latest_sale_at)}</span>
                            </div>
                        </div>

                        <div className="mt-5">
                            <DataTable
                                columns={salesColumns}
                                data={sales.items}
                                emptyMessage="No sales matched the current filters."
                                loading={salesLoading || dashboardLoading}
                                onPageChange={async (page) => {
                                    const nextFilters = {
                                        ...salesFilters,
                                        page,
                                    };

                                    setSalesFilters(nextFilters);
                                    await loadSalesEvent(nextFilters);
                                }}
                                onPerPageChange={async (perPage) => {
                                    const nextFilters = {
                                        ...salesFilters,
                                        page: 1,
                                        per_page: perPage,
                                    };

                                    setSalesFilters(nextFilters);
                                    await loadSalesEvent(nextFilters);
                                }}
                                pagination={sales.pagination}
                            />
                        </div>
                    </div>
                </section>
            ) : null}

            {activeTab === 'pharmacies' ? (
                <section className="mt-6">
                    <div className="glass-panel p-6">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p className="eyebrow">Pharmacy List</p>
                                <h2 className="mt-2 font-display text-2xl font-semibold text-slate-950">Search and Edit Pharmacies</h2>
                            </div>
                            <div className="flex flex-wrap gap-3">
                                <div className="rounded-full bg-slate-100 px-4 py-2 text-sm font-medium text-slate-600">
                                    {formatCount(pharmacies.pagination.total)} records
                                </div>
                                <button
                                    className="inline-flex items-center gap-2 rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white"
                                    onClick={() => {
                                        void requestPharmacyDownload('xlsx');
                                    }}
                                    type="button"
                                >
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path d="M7 4h7l5 5v11H7z" strokeLinecap="round" strokeLinejoin="round" />
                                        <path d="M14 4v5h5" strokeLinecap="round" strokeLinejoin="round" />
                                        <path d="M9 16l2-3 2 3 2-3" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                    Excel Download
                                </button>
                                <button
                                    className="inline-flex items-center gap-2 rounded-full bg-[#21345b] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#172741]"
                                    onClick={openPharmacyCreateModal}
                                    type="button"
                                >
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path d="M12 5v14M5 12h14" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                    Add Pharmacy
                                </button>
                            </div>
                        </div>

                        <div className="mt-5 grid gap-4 md:grid-cols-[minmax(0,1fr)_220px_220px]">
                            <label className="field-shell">
                                <span className="field-label">Search</span>
                                <input
                                    className="field-input"
                                    name="search"
                                    onChange={handlePharmacyFilterChange}
                                    placeholder="Search by name, code, or license"
                                    value={pharmacyFilters.search}
                                />
                            </label>

                            <label className="field-shell">
                                <span className="field-label">Organization</span>
                                <select className="field-input" name="tenant_id" onChange={handlePharmacyFilterChange} value={pharmacyFilters.tenant_id}>
                                    <option value="">All organizations</option>
                                    {catalog.filters.tenants.map((tenant) => (
                                        <option key={tenant.id} value={tenant.id}>
                                            {tenant.name}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            <label className="field-shell">
                                <span className="field-label">Hospital</span>
                                <select className="field-input" name="hospital_id" onChange={handlePharmacyFilterChange} value={pharmacyFilters.hospital_id}>
                                    <option value="">All hospitals</option>
                                    {filteredStudioHospitals.map((hospital) => (
                                        <option key={hospital.id} value={hospital.id}>
                                            {hospital.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>

                        <div className="mt-4 flex flex-wrap gap-3">
                            <button
                                className="inline-flex items-center gap-2 rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white"
                                onClick={() => {
                                    void loadPharmaciesEvent(pharmacyFilters);
                                }}
                                type="button"
                            >
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                    <path d="M4 12h16M12 4v16" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                                Load List
                            </button>
                            <button
                                className="inline-flex items-center gap-2 rounded-full border border-transparent px-5 py-3 text-sm font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                                onClick={() => {
                                    const nextFilters = initialPharmacyFilters();
                                    setPharmacyFilters(nextFilters);
                                    void loadPharmaciesEvent(nextFilters);
                                }}
                                type="button"
                            >
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                    <path d="m6 6 12 12M18 6 6 18" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                                Clear Filters
                            </button>
                        </div>

                        <div className="mt-5 rounded-[1.5rem] border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
                            Delete is blocked when sales already exist. Hospital change is also blocked after sales exist.
                        </div>

                        <div className="mt-5">
                            <DataTable
                                columns={pharmacyColumns}
                                data={pharmacies.items}
                                emptyMessage="No pharmacies matched the current filters."
                                loading={pharmacyLoading || dashboardLoading}
                                onPageChange={async (page) => {
                                    const nextFilters = {
                                        ...pharmacyFilters,
                                        page,
                                    };

                                    setPharmacyFilters(nextFilters);
                                    await loadPharmaciesEvent(nextFilters);
                                }}
                                onPerPageChange={async (perPage) => {
                                    const nextFilters = {
                                        ...pharmacyFilters,
                                        page: 1,
                                        per_page: perPage,
                                    };

                                    setPharmacyFilters(nextFilters);
                                    await loadPharmaciesEvent(nextFilters);
                                }}
                                pagination={pharmacies.pagination}
                            />
                        </div>
                    </div>
                </section>
            ) : null}

            {activeTab === 'exports' ? (
                <section className="mt-6 grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
                    <div className="glass-panel p-6">
                        <p className="eyebrow">Current Job</p>
                        <h2 className="mt-2 font-display text-2xl font-semibold text-slate-950">Latest Export</h2>

                        <div className="mt-5">
                            {activeExport ? (
                                <ExportCard item={activeExport} />
                            ) : (
                                <div className="rounded-3xl border border-dashed border-slate-300 bg-white/50 px-5 py-8 text-center text-sm text-slate-500">
                                    Create an export from the report page and it will appear here.
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="glass-panel p-6">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="eyebrow">Recent Files</p>
                                <h2 className="mt-2 font-display text-2xl font-semibold text-slate-950">Export History</h2>
                            </div>
                        </div>

                        <div className="mt-5 space-y-4">
                            {catalog.recent_exports.map((item) => (
                                <ExportCard item={item} key={item.id} />
                            ))}

                            {!catalog.recent_exports.length && !dashboardLoading ? (
                                <div className="rounded-3xl border border-dashed border-slate-300 bg-white/50 px-5 py-8 text-center text-sm text-slate-500">
                                    No export files yet.
                                </div>
                            ) : null}
                        </div>
                    </div>
                </section>
            ) : null}

            <ModalShell
                onClose={resetSaleForm}
                open={saleModalOpen}
                title={editingSaleItemId ? 'Edit Sale' : 'Add Sale'}
            >
                <form className="space-y-5" onSubmit={handleSaleSubmit}>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <label className="field-shell">
                            <span className="field-label">Pharmacy</span>
                            <select className="field-input" name="pharmacy_id" onChange={handleSaleFormChange} value={saleForm.pharmacy_id}>
                                <option value="">Choose a pharmacy</option>
                                {previewPharmacies.map((pharmacy) => (
                                    <option key={pharmacy.id} value={pharmacy.id}>
                                        {pharmacy.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="field-shell">
                            <span className="field-label">Medicine</span>
                            <select className="field-input" name="medicine_id" onChange={handleSaleFormChange} value={saleForm.medicine_id}>
                                <option value="">Choose a medicine</option>
                                {catalog.filters.medicines.map((medicine) => (
                                    <option key={medicine.id} value={medicine.id}>
                                        {medicine.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="field-shell">
                            <span className="field-label">Invoice Number</span>
                            <input className="field-input" name="invoice_number" onChange={handleSaleFormChange} value={saleForm.invoice_number} />
                        </label>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <label className="field-shell">
                            <span className="field-label">Sold At</span>
                            <input className="field-input" name="sold_at" onChange={handleSaleFormChange} type="datetime-local" value={saleForm.sold_at} />
                        </label>
                        <label className="field-shell">
                            <span className="field-label">Payment Method</span>
                            <select className="field-input" name="payment_method" onChange={handleSaleFormChange} value={saleForm.payment_method}>
                                {catalog.filters.payment_methods.map((item) => (
                                    <option key={item.value} value={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="field-shell">
                            <span className="field-label">Payment Status</span>
                            <select className="field-input" name="payment_status" onChange={handleSaleFormChange} value={saleForm.payment_status}>
                                {catalog.filters.payment_statuses.map((item) => (
                                    <option key={item.value} value={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="field-shell">
                            <span className="field-label">Batch Number</span>
                            <input className="field-input" name="batch_number" onChange={handleSaleFormChange} value={saleForm.batch_number} />
                        </label>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                        <label className="field-shell">
                            <span className="field-label">Quantity</span>
                            <input className="field-input" min="1" name="quantity" onChange={handleSaleFormChange} type="number" value={saleForm.quantity} />
                        </label>
                        <label className="field-shell">
                            <span className="field-label">Unit Price</span>
                            <input className="field-input" min="0" name="unit_price" onChange={handleSaleFormChange} step="0.01" type="number" value={saleForm.unit_price} />
                        </label>
                        <label className="field-shell">
                            <span className="field-label">Discount</span>
                            <input className="field-input" min="0" name="discount_amount" onChange={handleSaleFormChange} step="0.01" type="number" value={saleForm.discount_amount} />
                        </label>
                        <label className="field-shell">
                            <span className="field-label">Tax</span>
                            <input className="field-input" min="0" name="tax_amount" onChange={handleSaleFormChange} step="0.01" type="number" value={saleForm.tax_amount} />
                        </label>
                        <label className="field-shell">
                            <span className="field-label">Expires At</span>
                            <input className="field-input" name="expires_at" onChange={handleSaleFormChange} type="date" value={saleForm.expires_at} />
                        </label>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <button
                            className="inline-flex items-center rounded-full bg-[#21345b] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#172741] disabled:cursor-not-allowed disabled:bg-slate-400"
                            disabled={salesSubmitting}
                            type="submit"
                        >
                            {salesSubmitting ? 'Saving...' : editingSaleItemId ? 'Update Sale' : 'Create Sale'}
                        </button>
                        <button
                            className="inline-flex items-center rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white"
                            onClick={resetSaleForm}
                            type="button"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </ModalShell>

            <ModalShell
                onClose={resetPharmacyForm}
                open={pharmacyModalOpen}
                title={editingPharmacyId ? 'Edit Pharmacy' : 'Create Pharmacy'}
            >
                <form className="space-y-5" onSubmit={handlePharmacySubmit}>
                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="field-shell">
                            <span className="field-label">Hospital</span>
                            <select className="field-input" name="hospital_id" onChange={handlePharmacyFormChange} value={pharmacyForm.hospital_id}>
                                <option value="">Choose a hospital</option>
                                {hospitals.map((hospital) => (
                                    <option key={hospital.id} value={hospital.id}>
                                        {hospital.name}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <label className="field-shell">
                            <span className="field-label">Pharmacy Name</span>
                            <input className="field-input" name="name" onChange={handlePharmacyFormChange} value={pharmacyForm.name} />
                        </label>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <label className="field-shell">
                            <span className="field-label">Code</span>
                            <input className="field-input" name="code" onChange={handlePharmacyFormChange} value={pharmacyForm.code} />
                        </label>
                        <label className="field-shell">
                            <span className="field-label">License Number</span>
                            <input className="field-input" name="license_number" onChange={handlePharmacyFormChange} value={pharmacyForm.license_number} />
                        </label>
                        <label className="field-shell">
                            <span className="field-label">Contact Email</span>
                            <input className="field-input" name="contact_email" onChange={handlePharmacyFormChange} type="email" value={pharmacyForm.contact_email} />
                        </label>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <label className="field-shell">
                            <span className="field-label">Area</span>
                            <input className="field-input" name="area" onChange={handlePharmacyFormChange} value={pharmacyForm.area} />
                        </label>
                        <label className="field-shell">
                            <span className="field-label">City</span>
                            <input className="field-input" name="city" onChange={handlePharmacyFormChange} value={pharmacyForm.city} />
                        </label>
                        <label className="field-shell">
                            <span className="field-label">District</span>
                            <input className="field-input" name="district" onChange={handlePharmacyFormChange} value={pharmacyForm.district} />
                        </label>
                        <label className="field-shell">
                            <span className="field-label">Province</span>
                            <input className="field-input" name="province" onChange={handlePharmacyFormChange} value={pharmacyForm.province} />
                        </label>
                        <label className="field-shell">
                            <span className="field-label">Postal Code</span>
                            <input className="field-input" name="postal_code" onChange={handlePharmacyFormChange} value={pharmacyForm.postal_code} />
                        </label>
                        <label className="field-shell">
                            <span className="field-label">Email Domain</span>
                            <input className="field-input" name="email_domain" onChange={handlePharmacyFormChange} value={pharmacyForm.email_domain} />
                        </label>
                    </div>

                    <label className="flex items-center gap-3 rounded-3xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-700">
                        <input checked={pharmacyForm.seed_demo_sale} name="seed_demo_sale" onChange={handlePharmacyFormChange} type="checkbox" />
                        Add one demo sale now so the new pharmacy can appear in preview and export quickly.
                    </label>

                    <div className="flex flex-wrap gap-3">
                        <button
                            className="inline-flex items-center rounded-full bg-[#21345b] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#172741] disabled:cursor-not-allowed disabled:bg-slate-400"
                            disabled={pharmacySubmitting}
                            type="submit"
                        >
                            {pharmacySubmitting ? 'Saving...' : editingPharmacyId ? 'Update Pharmacy' : 'Create Pharmacy'}
                        </button>
                        <button
                            className="inline-flex items-center rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white"
                            onClick={resetPharmacyForm}
                            type="button"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </ModalShell>

            <ModalShell
                onClose={() => {
                    setDeleteSaleModalItem(null);
                }}
                open={Boolean(deleteSaleModalItem)}
                title="Delete Sale"
                widthClass="max-w-xl"
            >
                {deleteSaleModalItem ? (
                    <div>
                        <p className="text-sm leading-7 text-slate-600">
                            Delete invoice <span className="font-semibold text-slate-950">{deleteSaleModalItem.invoice_number}</span> from {deleteSaleModalItem.pharmacy_name}?
                        </p>

                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                            <div className="data-pill">
                                <span className="data-pill-label">Medicine</span>
                                <span className="data-pill-value">{deleteSaleModalItem.brand_name}</span>
                            </div>
                            <div className="data-pill">
                                <span className="data-pill-label">Total</span>
                                <span className="data-pill-value">{formatCurrency(deleteSaleModalItem.line_total)}</span>
                            </div>
                        </div>

                        <div className="mt-6 flex flex-wrap gap-3">
                            <button
                                className="inline-flex items-center rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700"
                                onClick={() => {
                                    void handleDeleteSale(deleteSaleModalItem.sale_item_id);
                                }}
                                type="button"
                            >
                                Delete Sale
                            </button>
                            <button
                                className="inline-flex items-center rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white"
                                onClick={() => {
                                    setDeleteSaleModalItem(null);
                                }}
                                type="button"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                ) : null}
            </ModalShell>

            <ModalShell
                onClose={() => {
                    setDeleteModalItem(null);
                }}
                open={Boolean(deleteModalItem)}
                title="Delete Pharmacy"
                widthClass="max-w-xl"
            >
                {deleteModalItem ? (
                    <div>
                        <p className="text-sm leading-7 text-slate-600">
                            Delete <span className="font-semibold text-slate-950">{deleteModalItem.name}</span>? This will fail if the pharmacy already has sales.
                        </p>

                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                            <div className="data-pill">
                                <span className="data-pill-label">Code</span>
                                <span className="data-pill-value">{deleteModalItem.code}</span>
                            </div>
                            <div className="data-pill">
                                <span className="data-pill-label">Hospital</span>
                                <span className="data-pill-value">{deleteModalItem.hospital_name}</span>
                            </div>
                        </div>

                        <div className="mt-6 flex flex-wrap gap-3">
                            <button
                                className="inline-flex items-center rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700"
                                onClick={() => {
                                    void handleDeletePharmacy(deleteModalItem.id);
                                }}
                                type="button"
                            >
                                Delete Pharmacy
                            </button>
                            <button
                                className="inline-flex items-center rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white"
                                onClick={() => {
                                    setDeleteModalItem(null);
                                }}
                                type="button"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                ) : null}
            </ModalShell>
            </main>
        </div>
    );
}
