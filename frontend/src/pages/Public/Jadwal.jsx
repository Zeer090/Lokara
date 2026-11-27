import React, { useState, useRef } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/klien.js';

const fetchLokasiList = async () => {
  const res = await api.get('/lokasi');
  return res.data?.data || res.data || [];
};

export default function Jadwal() {
  const calendarRef = useRef(null);
  const [lokasiId, setLokasiId] = useState('');
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [currentMonth, setCurrentMonth] = useState(() => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  });

  const { data: daftarLokasi = [] } = useQuery({
    queryKey: ['lokasi'],
    queryFn: fetchLokasiList,
  });

  // Query events from GET /api/jadwal/kalender
  const { data: events = [], isLoading, refetch } = useQuery({
    queryKey: ['kalender_publik', currentMonth, lokasiId],
    queryFn: async () => {
      const params = { bulan: currentMonth };
      if (lokasiId) params.lokasi_id = lokasiId;
      const res = await api.get('/jadwal/kalender', { params });
      return res.data?.events || res.data?.data || res.data || [];
    },
  });

  const handleDatesSet = (dateInfo) => {
    // Sync currentMonth when user clicks Next/Prev in calendar
    const d = dateInfo.view.currentStart;
    const yyyyMm = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    if (yyyyMm !== currentMonth) {
      setCurrentMonth(yyyyMm);
    }
  };

  const handleEventClick = (clickInfo) => {
    setSelectedEvent({
      title: clickInfo.event.title,
      start: clickInfo.event.start?.toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'short' }),
      end: clickInfo.event.end?.toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'short' }),
      lokasi: clickInfo.event.extendedProps?.lokasi || 'Lokasi Kampus',
      sumber: clickInfo.event.extendedProps?.sumber,
    });
  };

  return (
    <div className="min-h-screen bg-slate-50 py-10 px-4 sm:px-6 lg:px-8">
      <div className="max-w-7xl mx-auto space-y-6">
        {/* Header Title */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-slate-900 tracking-tight mb-1">
              Kalender Jadwal Ketersediaan
            </h1>
            <p className="text-xs sm:text-sm text-slate-500">
              Pantau jadwal terisi secara transparan. Privasi peminjam terlindungi (hanya menampilkan blok waktu terpakai).
            </p>
          </div>
          <div className="flex items-center gap-2">
            <a
              href="/app/ajukan"
              className="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-sm transition"
            >
              + Ajukan Peminjaman
            </a>
          </div>
        </div>

        {/* Filter and Legend Bar (21st Clean UI Style) */}
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
          {/* Dropdown Filter Lokasi */}
          <div className="flex items-center gap-3">
            <label className="text-xs font-semibold text-slate-700 whitespace-nowrap">
              Filter Lokasi:
            </label>
            <select
              value={lokasiId}
              onChange={(e) => setLokasiId(e.target.value)}
              className="border border-slate-300 rounded-xl px-3 py-2 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium text-slate-700 min-w-[200px]"
            >
              <option value="">-- Semua Lokasi &amp; Gedung --</option>
              {daftarLokasi.map((l) => (
                <option key={l.id} value={l.id}>
                  {l.jenis === 'gedung' ? '🏛️' : '🚪'} {l.nama}
                </option>
              ))}
            </select>
          </div>

          {/* Color Legend */}
          <div className="flex items-center gap-4 text-xs font-medium text-slate-600 flex-wrap">
            <div className="flex items-center gap-1.5">
              <span className="w-3 h-3 rounded-full bg-emerald-600"></span>
              <span>Peminjaman Terjadwal</span>
            </div>
            <div className="flex items-center gap-1.5">
              <span className="w-3 h-3 rounded-full bg-blue-600"></span>
              <span>Acara Resmi Kampus</span>
            </div>
            <div className="flex items-center gap-1.5">
              <span className="w-3 h-3 rounded-full bg-slate-200 border border-slate-300"></span>
              <span>Jadwal Kosong (Bebas Diajukan)</span>
            </div>
          </div>
        </div>

        {/* FullCalendar Card Container */}
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 overflow-hidden">
          {isLoading && (
            <div className="p-2 text-center text-xs text-blue-600 font-medium">
              Memperbarui slot jadwal dari database...
            </div>
          )}

          <div className="smartspace-calendar">
            <FullCalendar
              ref={calendarRef}
              plugins={[dayGridPlugin, timeGridPlugin]}
              initialView="dayGridMonth"
              locale="id"
              headerToolbar={{
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay',
              }}
              buttonText={{
                today: 'Hari Ini',
                month: 'Bulan',
                week: 'Minggu',
                day: 'Hari',
              }}
              events={events}
              datesSet={handleDatesSet}
              eventClick={handleEventClick}
              height="auto"
              slotMinTime="07:00:00"
              slotMaxTime="22:00:00"
              allDaySlot={false}
              dayMaxEvents={3}
              eventTimeFormat={{
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
              }}
            />
          </div>
        </div>

        {/* Modal Detail Event Klik */}
        {selectedEvent && (
          <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
            <div className="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-sm p-6 space-y-4">
              <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                <span className={`text-[10px] uppercase font-bold px-2 py-0.5 rounded-full ${
                  selectedEvent.sumber === 'acara_kampus' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700'
                }`}>
                  {selectedEvent.title}
                </span>
                <button
                  onClick={() => setSelectedEvent(null)}
                  className="text-slate-400 hover:text-slate-700 text-lg font-bold"
                >
                  ×
                </button>
              </div>

              <div className="space-y-3 text-xs">
                <div>
                  <span className="text-slate-400 block mb-0.5">Lokasi:</span>
                  <p className="font-bold text-slate-800 text-sm">{selectedEvent.lokasi}</p>
                </div>
                <div>
                  <span className="text-slate-400 block mb-0.5">Mulai:</span>
                  <p className="font-medium text-slate-800">{selectedEvent.start}</p>
                </div>
                <div>
                  <span className="text-slate-400 block mb-0.5">Selesai:</span>
                  <p className="font-medium text-slate-800">{selectedEvent.end}</p>
                </div>
                <div className="p-3 bg-slate-50 rounded-xl text-[11px] text-slate-500 border border-slate-200/60 leading-relaxed">
                  🔒 <em>Keterangan Privasi:</em> Informasi nama pemesan dan keperluan acara dilindungi dan tidak ditampilkan pada jadwal publik.
                </div>
              </div>

              <button
                onClick={() => setSelectedEvent(null)}
                className="w-full bg-slate-800 hover:bg-slate-900 text-white py-2 rounded-xl text-xs font-semibold transition"
              >
                Tutup
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
