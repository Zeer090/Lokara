--
-- PostgreSQL database dump
--

\restrict 7o200wdheRAAJTYxXyZoaUmkfINjbmXKv6YhpnNPgj2cXsBF8d75pc1nwFyWcQY

-- Dumped from database version 17.11
-- Dumped by pg_dump version 17.11

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: btree_gist; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS btree_gist WITH SCHEMA public;


--
-- Name: EXTENSION btree_gist; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION btree_gist IS 'support for indexing common datatypes in GiST';


--
-- Name: pgcrypto; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;


--
-- Name: EXTENSION pgcrypto; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION pgcrypto IS 'cryptographic functions';


--
-- Name: jenis_lokasi; Type: TYPE; Schema: public; Owner: smartspace_app
--

CREATE TYPE public.jenis_lokasi AS ENUM (
    'gedung',
    'ruangan'
);


ALTER TYPE public.jenis_lokasi OWNER TO smartspace_app;

--
-- Name: pengirim_pesan; Type: TYPE; Schema: public; Owner: smartspace_app
--

CREATE TYPE public.pengirim_pesan AS ENUM (
    'pengguna',
    'agent',
    'sistem'
);


ALTER TYPE public.pengirim_pesan OWNER TO smartspace_app;

--
-- Name: peran_pengguna; Type: TYPE; Schema: public; Owner: smartspace_app
--

CREATE TYPE public.peran_pengguna AS ENUM (
    'pengguna',
    'admin',
    'pimpinan'
);


ALTER TYPE public.peran_pengguna OWNER TO smartspace_app;

--
-- Name: status_kasus; Type: TYPE; Schema: public; Owner: smartspace_app
--

CREATE TYPE public.status_kasus AS ENUM (
    'terbuka',
    'diputuskan',
    'diajukan_ulang',
    'selesai',
    'diserahkan'
);


ALTER TYPE public.status_kasus OWNER TO smartspace_app;

--
-- Name: status_memo; Type: TYPE; Schema: public; Owner: smartspace_app
--

CREATE TYPE public.status_memo AS ENUM (
    'berlaku',
    'tidak_berlaku'
);


ALTER TYPE public.status_memo OWNER TO smartspace_app;

--
-- Name: status_reservasi; Type: TYPE; Schema: public; Owner: smartspace_app
--

CREATE TYPE public.status_reservasi AS ENUM (
    'menunggu',
    'disetujui',
    'tergusur',
    'selesai',
    'ditolak',
    'dibatalkan'
);


ALTER TYPE public.status_reservasi OWNER TO smartspace_app;

--
-- Name: status_usulan; Type: TYPE; Schema: public; Owner: smartspace_app
--

CREATE TYPE public.status_usulan AS ENUM (
    'diusulkan',
    'dipilih',
    'ditolak_pengguna',
    'kedaluwarsa'
);


ALTER TYPE public.status_usulan OWNER TO smartspace_app;

--
-- Name: cegah_ubah_log_agent(); Type: FUNCTION; Schema: public; Owner: smartspace_app
--

CREATE FUNCTION public.cegah_ubah_log_agent() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
      RAISE EXCEPTION 'log_agent bersifat hanya-tambah: % tidak diizinkan', TG_OP;
    END;
    $$;


ALTER FUNCTION public.cegah_ubah_log_agent() OWNER TO smartspace_app;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: acara_kampus; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.acara_kampus (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    nama character varying(200) NOT NULL,
    lokasi_id uuid NOT NULL,
    mulai timestamp with time zone NOT NULL,
    selesai timestamp with time zone NOT NULL,
    alasan text NOT NULL,
    didaftarkan_oleh uuid NOT NULL,
    dibuat_pada timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT acara_kampus_check CHECK ((selesai > mulai))
);


ALTER TABLE public.acara_kampus OWNER TO smartspace_app;

--
-- Name: cache; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache OWNER TO smartspace_app;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO smartspace_app;

--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO smartspace_app;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: smartspace_app
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO smartspace_app;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: smartspace_app
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO smartspace_app;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO smartspace_app;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: smartspace_app
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO smartspace_app;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: smartspace_app
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: kasus_penggusuran; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.kasus_penggusuran (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    reservasi_id uuid NOT NULL,
    acara_id uuid,
    alasan text NOT NULL,
    dibuat_oleh uuid NOT NULL,
    status public.status_kasus DEFAULT 'terbuka'::public.status_kasus NOT NULL,
    catatan_keputusan jsonb,
    reservasi_pengganti_id uuid,
    dibuat_pada timestamp with time zone DEFAULT now() NOT NULL,
    diputuskan_pada timestamp with time zone,
    selesai_pada timestamp with time zone
);


ALTER TABLE public.kasus_penggusuran OWNER TO smartspace_app;

--
-- Name: log_agent; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.log_agent (
    id bigint NOT NULL,
    kasus_id uuid NOT NULL,
    langkah integer NOT NULL,
    jenis character varying(30) NOT NULL,
    nama_fungsi character varying(60),
    masukan jsonb,
    hasil jsonb,
    catatan text,
    dibuat_pada timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.log_agent OWNER TO smartspace_app;

--
-- Name: log_agent_id_seq; Type: SEQUENCE; Schema: public; Owner: smartspace_app
--

CREATE SEQUENCE public.log_agent_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.log_agent_id_seq OWNER TO smartspace_app;

--
-- Name: log_agent_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: smartspace_app
--

ALTER SEQUENCE public.log_agent_id_seq OWNED BY public.log_agent.id;


--
-- Name: lokasi; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.lokasi (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    nama character varying(120) NOT NULL,
    jenis public.jenis_lokasi NOT NULL,
    kapasitas integer NOT NULL,
    fasilitas jsonb DEFAULT '{}'::jsonb NOT NULL,
    keterangan text,
    aktif boolean DEFAULT true NOT NULL,
    CONSTRAINT lokasi_kapasitas_check CHECK ((kapasitas > 0))
);


ALTER TABLE public.lokasi OWNER TO smartspace_app;

--
-- Name: memo; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.memo (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    reservasi_id uuid NOT NULL,
    nomor_memo character varying(60) NOT NULL,
    path_berkas text,
    status public.status_memo DEFAULT 'berlaku'::public.status_memo NOT NULL,
    diterbitkan_pada timestamp with time zone DEFAULT now() NOT NULL,
    dicabut_pada timestamp with time zone,
    alasan_cabut text
);


ALTER TABLE public.memo OWNER TO smartspace_app;

--
-- Name: migrations; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO smartspace_app;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: smartspace_app
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO smartspace_app;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: smartspace_app
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: notifikasi; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.notifikasi (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    pengguna_id uuid NOT NULL,
    judul character varying(160) NOT NULL,
    isi text NOT NULL,
    tautan text,
    sudah_dibaca boolean DEFAULT false NOT NULL,
    dibuat_pada timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.notifikasi OWNER TO smartspace_app;

--
-- Name: pengaturan; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.pengaturan (
    kunci character varying(60) NOT NULL,
    nilai text NOT NULL
);


ALTER TABLE public.pengaturan OWNER TO smartspace_app;

--
-- Name: pengguna; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.pengguna (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    nama character varying(120) NOT NULL,
    email character varying(160) NOT NULL,
    password_hash text NOT NULL,
    peran public.peran_pengguna DEFAULT 'pengguna'::public.peran_pengguna NOT NULL,
    organisasi character varying(160),
    no_hp character varying(20),
    aktif boolean DEFAULT true NOT NULL,
    dibuat_pada timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.pengguna OWNER TO smartspace_app;

--
-- Name: percakapan_info; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.percakapan_info (
    id bigint NOT NULL,
    pengguna_id uuid,
    sesi character varying(60) NOT NULL,
    peran character varying(10) NOT NULL,
    isi text NOT NULL,
    dibuat_pada timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT percakapan_info_peran_check CHECK (((peran)::text = ANY ((ARRAY['pengguna'::character varying, 'asisten'::character varying])::text[])))
);


ALTER TABLE public.percakapan_info OWNER TO smartspace_app;

--
-- Name: percakapan_info_id_seq; Type: SEQUENCE; Schema: public; Owner: smartspace_app
--

CREATE SEQUENCE public.percakapan_info_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.percakapan_info_id_seq OWNER TO smartspace_app;

--
-- Name: percakapan_info_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: smartspace_app
--

ALTER SEQUENCE public.percakapan_info_id_seq OWNED BY public.percakapan_info.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id uuid NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.personal_access_tokens OWNER TO smartspace_app;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: smartspace_app
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.personal_access_tokens_id_seq OWNER TO smartspace_app;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: smartspace_app
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: pesan_negosiasi; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.pesan_negosiasi (
    id bigint NOT NULL,
    kasus_id uuid NOT NULL,
    pengirim public.pengirim_pesan NOT NULL,
    isi text NOT NULL,
    dibuat_pada timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.pesan_negosiasi OWNER TO smartspace_app;

--
-- Name: pesan_negosiasi_id_seq; Type: SEQUENCE; Schema: public; Owner: smartspace_app
--

CREATE SEQUENCE public.pesan_negosiasi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pesan_negosiasi_id_seq OWNER TO smartspace_app;

--
-- Name: pesan_negosiasi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: smartspace_app
--

ALTER SEQUENCE public.pesan_negosiasi_id_seq OWNED BY public.pesan_negosiasi.id;


--
-- Name: reservasi; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.reservasi (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    kode character varying(20) NOT NULL,
    pengguna_id uuid NOT NULL,
    lokasi_id uuid NOT NULL,
    surat_id uuid,
    keperluan text NOT NULL,
    kebutuhan jsonb DEFAULT '{}'::jsonb NOT NULL,
    mulai timestamp with time zone NOT NULL,
    selesai timestamp with time zone NOT NULL,
    status public.status_reservasi DEFAULT 'menunggu'::public.status_reservasi NOT NULL,
    catatan_admin text,
    reservasi_asal_id uuid,
    dibuat_pada timestamp with time zone DEFAULT now() NOT NULL,
    diperbarui_pada timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT reservasi_check CHECK ((selesai > mulai))
);


ALTER TABLE public.reservasi OWNER TO smartspace_app;

--
-- Name: slot_terkunci; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.slot_terkunci (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    lokasi_id uuid NOT NULL,
    rentang tstzrange NOT NULL,
    sumber character varying(20) NOT NULL,
    reservasi_id uuid,
    acara_id uuid,
    CONSTRAINT slot_terkunci_check CHECK (((((sumber)::text = 'reservasi'::text) AND (reservasi_id IS NOT NULL) AND (acara_id IS NULL)) OR (((sumber)::text = 'acara_kampus'::text) AND (acara_id IS NOT NULL) AND (reservasi_id IS NULL)))),
    CONSTRAINT slot_terkunci_sumber_check CHECK (((sumber)::text = ANY ((ARRAY['reservasi'::character varying, 'acara_kampus'::character varying])::text[])))
);


ALTER TABLE public.slot_terkunci OWNER TO smartspace_app;

--
-- Name: surat; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.surat (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    pengguna_id uuid NOT NULL,
    nama_asli character varying(255) NOT NULL,
    path_berkas text NOT NULL,
    ukuran_byte integer NOT NULL,
    dibuat_pada timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT surat_ukuran_byte_check CHECK ((ukuran_byte > 0))
);


ALTER TABLE public.surat OWNER TO smartspace_app;

--
-- Name: usulan_agent; Type: TABLE; Schema: public; Owner: smartspace_app
--

CREATE TABLE public.usulan_agent (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    kasus_id uuid NOT NULL,
    lokasi_id uuid NOT NULL,
    mulai timestamp with time zone NOT NULL,
    selesai timestamp with time zone NOT NULL,
    alasan_usulan text,
    status public.status_usulan DEFAULT 'diusulkan'::public.status_usulan NOT NULL,
    bentrok_dicek boolean DEFAULT false NOT NULL,
    dibuat_pada timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT usulan_agent_check CHECK ((selesai > mulai))
);


ALTER TABLE public.usulan_agent OWNER TO smartspace_app;

--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: log_agent id; Type: DEFAULT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.log_agent ALTER COLUMN id SET DEFAULT nextval('public.log_agent_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: percakapan_info id; Type: DEFAULT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.percakapan_info ALTER COLUMN id SET DEFAULT nextval('public.percakapan_info_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: pesan_negosiasi id; Type: DEFAULT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.pesan_negosiasi ALTER COLUMN id SET DEFAULT nextval('public.pesan_negosiasi_id_seq'::regclass);


--
-- Data for Name: acara_kampus; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.acara_kampus (id, nama, lokasi_id, mulai, selesai, alasan, didaftarkan_oleh, dibuat_pada) FROM stdin;
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: kasus_penggusuran; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.kasus_penggusuran (id, reservasi_id, acara_id, alasan, dibuat_oleh, status, catatan_keputusan, reservasi_pengganti_id, dibuat_pada, diputuskan_pada, selesai_pada) FROM stdin;
\.


--
-- Data for Name: log_agent; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.log_agent (id, kasus_id, langkah, jenis, nama_fungsi, masukan, hasil, catatan, dibuat_pada) FROM stdin;
\.


--
-- Data for Name: lokasi; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.lokasi (id, nama, jenis, kapasitas, fasilitas, keterangan, aktif) FROM stdin;
\.


--
-- Data for Name: memo; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.memo (id, reservasi_id, nomor_memo, path_berkas, status, diterbitkan_pada, dicabut_pada, alasan_cabut) FROM stdin;
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000001_create_cache_table	1
2	0001_01_01_000002_create_jobs_table	1
3	2026_01_01_000001_create_ekstensi_enum	1
4	2026_01_01_000002_create_pengguna_lokasi	1
5	2026_01_01_000003_create_surat_reservasi	1
6	2026_01_01_000004_create_acara_slot	1
7	2026_01_01_000005_create_memo_kasus	1
8	2026_01_01_000006_create_pesan_usulan_log	1
9	2026_01_01_000007_create_notifikasi_pengaturan_info	1
10	2026_10_29_101157_create_personal_access_tokens_table	1
\.


--
-- Data for Name: notifikasi; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.notifikasi (id, pengguna_id, judul, isi, tautan, sudah_dibaca, dibuat_pada) FROM stdin;
\.


--
-- Data for Name: pengaturan; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.pengaturan (kunci, nilai) FROM stdin;
\.


--
-- Data for Name: pengguna; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.pengguna (id, nama, email, password_hash, peran, organisasi, no_hp, aktif, dibuat_pada) FROM stdin;
\.


--
-- Data for Name: percakapan_info; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.percakapan_info (id, pengguna_id, sesi, peran, isi, dibuat_pada) FROM stdin;
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: pesan_negosiasi; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.pesan_negosiasi (id, kasus_id, pengirim, isi, dibuat_pada) FROM stdin;
\.


--
-- Data for Name: reservasi; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.reservasi (id, kode, pengguna_id, lokasi_id, surat_id, keperluan, kebutuhan, mulai, selesai, status, catatan_admin, reservasi_asal_id, dibuat_pada, diperbarui_pada) FROM stdin;
\.


--
-- Data for Name: slot_terkunci; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.slot_terkunci (id, lokasi_id, rentang, sumber, reservasi_id, acara_id) FROM stdin;
\.


--
-- Data for Name: surat; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.surat (id, pengguna_id, nama_asli, path_berkas, ukuran_byte, dibuat_pada) FROM stdin;
\.


--
-- Data for Name: usulan_agent; Type: TABLE DATA; Schema: public; Owner: smartspace_app
--

COPY public.usulan_agent (id, kasus_id, lokasi_id, mulai, selesai, alasan_usulan, status, bentrok_dicek, dibuat_pada) FROM stdin;
\.


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: smartspace_app
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: smartspace_app
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: log_agent_id_seq; Type: SEQUENCE SET; Schema: public; Owner: smartspace_app
--

SELECT pg_catalog.setval('public.log_agent_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: smartspace_app
--

SELECT pg_catalog.setval('public.migrations_id_seq', 10, true);


--
-- Name: percakapan_info_id_seq; Type: SEQUENCE SET; Schema: public; Owner: smartspace_app
--

SELECT pg_catalog.setval('public.percakapan_info_id_seq', 1, false);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: smartspace_app
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 145, true);


--
-- Name: pesan_negosiasi_id_seq; Type: SEQUENCE SET; Schema: public; Owner: smartspace_app
--

SELECT pg_catalog.setval('public.pesan_negosiasi_id_seq', 1, false);


--
-- Name: acara_kampus acara_kampus_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.acara_kampus
    ADD CONSTRAINT acara_kampus_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: kasus_penggusuran kasus_penggusuran_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.kasus_penggusuran
    ADD CONSTRAINT kasus_penggusuran_pkey PRIMARY KEY (id);


--
-- Name: kasus_penggusuran kasus_penggusuran_reservasi_id_key; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.kasus_penggusuran
    ADD CONSTRAINT kasus_penggusuran_reservasi_id_key UNIQUE (reservasi_id);


--
-- Name: log_agent log_agent_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.log_agent
    ADD CONSTRAINT log_agent_pkey PRIMARY KEY (id);


--
-- Name: lokasi lokasi_nama_key; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.lokasi
    ADD CONSTRAINT lokasi_nama_key UNIQUE (nama);


--
-- Name: lokasi lokasi_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.lokasi
    ADD CONSTRAINT lokasi_pkey PRIMARY KEY (id);


--
-- Name: memo memo_nomor_memo_key; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.memo
    ADD CONSTRAINT memo_nomor_memo_key UNIQUE (nomor_memo);


--
-- Name: memo memo_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.memo
    ADD CONSTRAINT memo_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: notifikasi notifikasi_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.notifikasi
    ADD CONSTRAINT notifikasi_pkey PRIMARY KEY (id);


--
-- Name: pengaturan pengaturan_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.pengaturan
    ADD CONSTRAINT pengaturan_pkey PRIMARY KEY (kunci);


--
-- Name: pengguna pengguna_email_key; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.pengguna
    ADD CONSTRAINT pengguna_email_key UNIQUE (email);


--
-- Name: pengguna pengguna_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.pengguna
    ADD CONSTRAINT pengguna_pkey PRIMARY KEY (id);


--
-- Name: percakapan_info percakapan_info_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.percakapan_info
    ADD CONSTRAINT percakapan_info_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: pesan_negosiasi pesan_negosiasi_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.pesan_negosiasi
    ADD CONSTRAINT pesan_negosiasi_pkey PRIMARY KEY (id);


--
-- Name: reservasi reservasi_kode_key; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.reservasi
    ADD CONSTRAINT reservasi_kode_key UNIQUE (kode);


--
-- Name: reservasi reservasi_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.reservasi
    ADD CONSTRAINT reservasi_pkey PRIMARY KEY (id);


--
-- Name: slot_terkunci slot_terkunci_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.slot_terkunci
    ADD CONSTRAINT slot_terkunci_pkey PRIMARY KEY (id);


--
-- Name: surat surat_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.surat
    ADD CONSTRAINT surat_pkey PRIMARY KEY (id);


--
-- Name: slot_terkunci tidak_boleh_bentrok; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.slot_terkunci
    ADD CONSTRAINT tidak_boleh_bentrok EXCLUDE USING gist (lokasi_id WITH =, rentang WITH &&);


--
-- Name: usulan_agent usulan_agent_pkey; Type: CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.usulan_agent
    ADD CONSTRAINT usulan_agent_pkey PRIMARY KEY (id);


--
-- Name: idx_kasus_status; Type: INDEX; Schema: public; Owner: smartspace_app
--

CREATE INDEX idx_kasus_status ON public.kasus_penggusuran USING btree (status);


--
-- Name: idx_log_kasus; Type: INDEX; Schema: public; Owner: smartspace_app
--

CREATE INDEX idx_log_kasus ON public.log_agent USING btree (kasus_id, id);


--
-- Name: idx_memo_reservasi; Type: INDEX; Schema: public; Owner: smartspace_app
--

CREATE INDEX idx_memo_reservasi ON public.memo USING btree (reservasi_id);


--
-- Name: idx_notif_pengguna; Type: INDEX; Schema: public; Owner: smartspace_app
--

CREATE INDEX idx_notif_pengguna ON public.notifikasi USING btree (pengguna_id, sudah_dibaca);


--
-- Name: idx_pesan_kasus; Type: INDEX; Schema: public; Owner: smartspace_app
--

CREATE INDEX idx_pesan_kasus ON public.pesan_negosiasi USING btree (kasus_id, id);


--
-- Name: idx_reservasi_lokasi_waktu; Type: INDEX; Schema: public; Owner: smartspace_app
--

CREATE INDEX idx_reservasi_lokasi_waktu ON public.reservasi USING btree (lokasi_id, mulai, selesai);


--
-- Name: idx_reservasi_pengguna; Type: INDEX; Schema: public; Owner: smartspace_app
--

CREATE INDEX idx_reservasi_pengguna ON public.reservasi USING btree (pengguna_id);


--
-- Name: idx_reservasi_status; Type: INDEX; Schema: public; Owner: smartspace_app
--

CREATE INDEX idx_reservasi_status ON public.reservasi USING btree (status);


--
-- Name: idx_usulan_kasus; Type: INDEX; Schema: public; Owner: smartspace_app
--

CREATE INDEX idx_usulan_kasus ON public.usulan_agent USING btree (kasus_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: smartspace_app
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: smartspace_app
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: smartspace_app
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: uq_slot_acara; Type: INDEX; Schema: public; Owner: smartspace_app
--

CREATE UNIQUE INDEX uq_slot_acara ON public.slot_terkunci USING btree (acara_id) WHERE (acara_id IS NOT NULL);


--
-- Name: uq_slot_reservasi; Type: INDEX; Schema: public; Owner: smartspace_app
--

CREATE UNIQUE INDEX uq_slot_reservasi ON public.slot_terkunci USING btree (reservasi_id) WHERE (reservasi_id IS NOT NULL);


--
-- Name: log_agent trg_log_agent_hanya_tambah; Type: TRIGGER; Schema: public; Owner: smartspace_app
--

CREATE TRIGGER trg_log_agent_hanya_tambah BEFORE DELETE OR UPDATE ON public.log_agent FOR EACH ROW EXECUTE FUNCTION public.cegah_ubah_log_agent();


--
-- Name: acara_kampus acara_kampus_didaftarkan_oleh_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.acara_kampus
    ADD CONSTRAINT acara_kampus_didaftarkan_oleh_fkey FOREIGN KEY (didaftarkan_oleh) REFERENCES public.pengguna(id);


--
-- Name: acara_kampus acara_kampus_lokasi_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.acara_kampus
    ADD CONSTRAINT acara_kampus_lokasi_id_fkey FOREIGN KEY (lokasi_id) REFERENCES public.lokasi(id);


--
-- Name: kasus_penggusuran kasus_penggusuran_acara_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.kasus_penggusuran
    ADD CONSTRAINT kasus_penggusuran_acara_id_fkey FOREIGN KEY (acara_id) REFERENCES public.acara_kampus(id);


--
-- Name: kasus_penggusuran kasus_penggusuran_dibuat_oleh_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.kasus_penggusuran
    ADD CONSTRAINT kasus_penggusuran_dibuat_oleh_fkey FOREIGN KEY (dibuat_oleh) REFERENCES public.pengguna(id);


--
-- Name: kasus_penggusuran kasus_penggusuran_reservasi_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.kasus_penggusuran
    ADD CONSTRAINT kasus_penggusuran_reservasi_id_fkey FOREIGN KEY (reservasi_id) REFERENCES public.reservasi(id);


--
-- Name: kasus_penggusuran kasus_penggusuran_reservasi_pengganti_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.kasus_penggusuran
    ADD CONSTRAINT kasus_penggusuran_reservasi_pengganti_id_fkey FOREIGN KEY (reservasi_pengganti_id) REFERENCES public.reservasi(id);


--
-- Name: log_agent log_agent_kasus_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.log_agent
    ADD CONSTRAINT log_agent_kasus_id_fkey FOREIGN KEY (kasus_id) REFERENCES public.kasus_penggusuran(id) ON DELETE CASCADE;


--
-- Name: memo memo_reservasi_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.memo
    ADD CONSTRAINT memo_reservasi_id_fkey FOREIGN KEY (reservasi_id) REFERENCES public.reservasi(id);


--
-- Name: notifikasi notifikasi_pengguna_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.notifikasi
    ADD CONSTRAINT notifikasi_pengguna_id_fkey FOREIGN KEY (pengguna_id) REFERENCES public.pengguna(id) ON DELETE CASCADE;


--
-- Name: percakapan_info percakapan_info_pengguna_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.percakapan_info
    ADD CONSTRAINT percakapan_info_pengguna_id_fkey FOREIGN KEY (pengguna_id) REFERENCES public.pengguna(id);


--
-- Name: pesan_negosiasi pesan_negosiasi_kasus_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.pesan_negosiasi
    ADD CONSTRAINT pesan_negosiasi_kasus_id_fkey FOREIGN KEY (kasus_id) REFERENCES public.kasus_penggusuran(id) ON DELETE CASCADE;


--
-- Name: reservasi reservasi_lokasi_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.reservasi
    ADD CONSTRAINT reservasi_lokasi_id_fkey FOREIGN KEY (lokasi_id) REFERENCES public.lokasi(id);


--
-- Name: reservasi reservasi_pengguna_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.reservasi
    ADD CONSTRAINT reservasi_pengguna_id_fkey FOREIGN KEY (pengguna_id) REFERENCES public.pengguna(id);


--
-- Name: reservasi reservasi_reservasi_asal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.reservasi
    ADD CONSTRAINT reservasi_reservasi_asal_id_fkey FOREIGN KEY (reservasi_asal_id) REFERENCES public.reservasi(id);


--
-- Name: reservasi reservasi_surat_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.reservasi
    ADD CONSTRAINT reservasi_surat_id_fkey FOREIGN KEY (surat_id) REFERENCES public.surat(id);


--
-- Name: slot_terkunci slot_terkunci_acara_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.slot_terkunci
    ADD CONSTRAINT slot_terkunci_acara_id_fkey FOREIGN KEY (acara_id) REFERENCES public.acara_kampus(id) ON DELETE CASCADE;


--
-- Name: slot_terkunci slot_terkunci_lokasi_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.slot_terkunci
    ADD CONSTRAINT slot_terkunci_lokasi_id_fkey FOREIGN KEY (lokasi_id) REFERENCES public.lokasi(id);


--
-- Name: slot_terkunci slot_terkunci_reservasi_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.slot_terkunci
    ADD CONSTRAINT slot_terkunci_reservasi_id_fkey FOREIGN KEY (reservasi_id) REFERENCES public.reservasi(id) ON DELETE CASCADE;


--
-- Name: surat surat_pengguna_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.surat
    ADD CONSTRAINT surat_pengguna_id_fkey FOREIGN KEY (pengguna_id) REFERENCES public.pengguna(id);


--
-- Name: usulan_agent usulan_agent_kasus_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.usulan_agent
    ADD CONSTRAINT usulan_agent_kasus_id_fkey FOREIGN KEY (kasus_id) REFERENCES public.kasus_penggusuran(id) ON DELETE CASCADE;


--
-- Name: usulan_agent usulan_agent_lokasi_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: smartspace_app
--

ALTER TABLE ONLY public.usulan_agent
    ADD CONSTRAINT usulan_agent_lokasi_id_fkey FOREIGN KEY (lokasi_id) REFERENCES public.lokasi(id);


--
-- PostgreSQL database dump complete
--

\unrestrict 7o200wdheRAAJTYxXyZoaUmkfINjbmXKv6YhpnNPgj2cXsBF8d75pc1nwFyWcQY

