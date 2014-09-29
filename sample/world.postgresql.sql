--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: t_city_cit; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE IF NOT EXISTS "t_city_cit" (
    cit_id integer NOT NULL,
    cit_name text NOT NULL,
    cou_code character(3) NOT NULL,
    cit_district text NOT NULL,
    cit_population integer NOT NULL,
    last_modified timestamp NOT NULL
);


ALTER TABLE public."t_city_cit" OWNER TO postgres;

--
-- Name: t_countrylanguage_col; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE IF NOT EXISTS "t_countrylanguage_col" (
    cou_code character(3) NOT NULL,
    col_language text NOT NULL,
    col_isofficial boolean NOT NULL,
    col_percentage real NOT NULL
);


ALTER TABLE public."t_countrylanguage_col" OWNER TO postgres;

--
-- Name: t_country_cou; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE IF NOT EXISTS "t_country_cou" (
    cou_code character(3) NOT NULL,
    cou_name text NOT NULL,
    cou_continent text NOT NULL,
    cou_region text NOT NULL,
    cou_surfacearea real NOT NULL,
    cou_indepyear smallint,
    cou_population integer NOT NULL,
    cou_lifeexpectancy real,
    cou_gnp numeric(10,2),
    cou_gnpold numeric(10,2),
    cou_localname text NOT NULL,
    cou_governmentform text NOT NULL,
    cou_head_of_state text,
    cou_capital integer,
    cou_code2 character(2) NOT NULL,
    CONSTRAINT country_continent_check CHECK ((((((((cou_continent = 'Asia'::text) OR (cou_continent = 'Europe'::text)) OR (cou_continent = 'North America'::text)) OR (cou_continent = 'Africa'::text)) OR (cou_continent = 'Oceania'::text)) OR (cou_continent = 'Antarctica'::text)) OR (cou_continent = 'South America'::text)))
);


ALTER TABLE public."t_country_cou" OWNER TO postgres;

--
-- Data for Name: t_city_cit; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "t_city_cit" (cit_id, cit_name, cou_code, cit_district, cit_population, last_modified) FROM stdin;
1	Kabul	AFG	Kabol	1780000	2009-04-09 11:56:26
2	Qandahar	AFG	Qandahar	237500	2013-06-06 21:27:32
3	Herat	AFG	Herat	186800	2010-11-18 12:27:04
4	Mazar-e-Sharif	AFG	Balkh	127800	2013-04-13 19:33:15
5	Amsterdam	NLD	Noord-Holland	731200	2009-12-03 18:59:53
6	Rotterdam	NLD	Zuid-Holland	593321	2007-11-20 03:11:46
7	Haag	NLD	Zuid-Holland	440900	2004-02-08 07:35:08
8	Utrecht	NLD	Utrecht	234323	2002-07-07 18:06:19
9	Eindhoven	NLD	Noord-Brabant	201843	2002-10-16 23:09:29
10	Tilburg	NLD	Noord-Brabant	193238	2005-01-23 12:39:25
11	Groningen	NLD	Groningen	172701	2014-09-04 23:06:55
12	Breda	NLD	Noord-Brabant	160398	2013-02-20 01:33:12
13	Apeldoorn	NLD	Gelderland	153491	2012-04-09 01:04:33
14	Nijmegen	NLD	Gelderland	152463	2011-01-23 03:21:16
15	Enschede	NLD	Overijssel	149544	2001-07-26 10:03:05
16	Haarlem	NLD	Noord-Holland	148772	2014-11-15 15:47:31
17	Almere	NLD	Flevoland	142465	2013-10-26 02:02:22
18	Arnhem	NLD	Gelderland	138020	2005-02-12 23:11:14
19	Zaanstad	NLD	Noord-Holland	135621	2007-04-12 17:46:02
20	´s-Hertogenbosch	NLD	Noord-Brabant	129170	2002-07-13 02:21:41
21	Amersfoort	NLD	Utrecht	126270	2010-07-20 14:44:11
22	Maastricht	NLD	Limburg	122087	2008-05-16 17:31:53
23	Dordrecht	NLD	Zuid-Holland	119811	2004-04-28 15:28:39
24	Leiden	NLD	Zuid-Holland	117196	2014-06-17 08:04:15
25	Haarlemmermeer	NLD	Noord-Holland	110722	2013-12-28 04:13:59
26	Zoetermeer	NLD	Zuid-Holland	110214	2007-04-17 16:05:39
27	Emmen	NLD	Drenthe	105853	2002-12-02 16:30:09
28	Zwolle	NLD	Overijssel	105819	2002-02-15 08:32:16
29	Ede	NLD	Gelderland	101574	2012-07-11 19:29:20
30	Delft	NLD	Zuid-Holland	95268	2010-07-24 23:43:01
31	Heerlen	NLD	Limburg	95052	2012-05-06 21:31:36
32	Alkmaar	NLD	Noord-Holland	92713	2014-01-19 16:50:02
33	Willemstad	ANT	Curaçao	2345	2014-07-05 23:50:30
34	Tirana	ALB	Tirana	270000	2011-07-24 23:18:18
35	Alger	DZA	Alger	2168000	2012-06-07 19:14:54
36	Oran	DZA	Oran	609823	2010-09-09 20:47:58
37	Constantine	DZA	Constantine	443727	2003-06-11 17:36:18
38	Annaba	DZA	Annaba	222518	2008-05-12 11:27:51
39	Batna	DZA	Batna	183377	2001-12-23 14:32:14
40	Sétif	DZA	Sétif	179055	2011-12-04 01:52:08
41	Sidi Bel Abbès	DZA	Sidi Bel Abbès	153106	2012-06-07 12:16:14
42	Skikda	DZA	Skikda	128747	2013-06-15 03:17:38
43	Biskra	DZA	Biskra	128281	2006-10-13 04:22:04
44	Blida (el-Boulaida)	DZA	Blida	127284	2004-01-20 15:35:50
45	Béjaïa	DZA	Béjaïa	117162	2004-07-16 02:26:38
46	Mostaganem	DZA	Mostaganem	115212	2011-04-09 14:26:34
47	Tébessa	DZA	Tébessa	112007	2003-11-02 10:23:54
48	Tlemcen (Tilimsen)	DZA	Tlemcen	110242	2004-03-24 23:36:46
49	Béchar	DZA	Béchar	107311	2014-08-28 21:35:18
50	Tiaret	DZA	Tiaret	100118	2003-01-21 23:01:34
51	Ech-Chleff (el-Asnam)	DZA	Chlef	96794	2011-06-28 03:09:30
52	Ghardaïa	DZA	Ghardaïa	89415	2005-02-21 14:34:48
53	Tafuna	ASM	Tutuila	5200	2008-08-03 23:07:14
54	Fagatogo	ASM	Tutuila	2323	2003-01-20 13:27:07
55	Andorra la Vella	AND	Andorra la Vella	21189	2008-09-09 08:23:23
56	Luanda	AGO	Luanda	2022000	2008-06-15 23:13:02
57	Huambo	AGO	Huambo	163100	2006-09-21 20:17:07
58	Lobito	AGO	Benguela	130000	2009-01-26 10:46:35
59	Benguela	AGO	Benguela	128300	2007-03-11 14:40:06
60	Namibe	AGO	Namibe	118200	2010-09-23 02:57:04
61	South Hill	AIA		961	2009-05-09 06:55:56
62	The Valley	AIA		595	2013-12-21 14:27:21
63	Saint John´s	ATG	St John	24000	2012-12-17 04:15:27
64	Dubai	ARE	Dubai	669181	2004-08-16 19:27:15
65	Abu Dhabi	ARE	Abu Dhabi	398695	2003-03-17 21:38:33
66	Sharja	ARE	Sharja	320095	2010-01-08 10:07:44
67	al-Ayn	ARE	Abu Dhabi	225970	2006-10-06 02:44:11
68	Ajman	ARE	Ajman	114395	2001-09-16 11:15:23
69	Buenos Aires	ARG	Distrito Federal	2982146	2001-05-06 15:14:27
70	La Matanza	ARG	Buenos Aires	1266461	2003-08-11 14:14:43
71	Córdoba	ARG	Córdoba	1157507	2005-02-28 07:48:15
72	Rosario	ARG	Santa Fé	907718	2001-02-14 10:22:33
73	Lomas de Zamora	ARG	Buenos Aires	622013	2003-12-07 10:27:25
74	Quilmes	ARG	Buenos Aires	559249	2013-04-13 15:58:52
75	Almirante Brown	ARG	Buenos Aires	538918	2013-12-10 06:54:04
76	La Plata	ARG	Buenos Aires	521936	2007-02-19 22:51:53
77	Mar del Plata	ARG	Buenos Aires	512880	2010-01-17 09:21:04
78	San Miguel de Tucumán	ARG	Tucumán	470809	2008-11-11 13:08:48
79	Lanús	ARG	Buenos Aires	469735	2001-11-24 10:49:29
80	Merlo	ARG	Buenos Aires	463846	2012-02-28 20:35:13
81	General San Martín	ARG	Buenos Aires	422542	2003-06-04 18:27:47
82	Salta	ARG	Salta	367550	2003-12-05 15:12:23
83	Moreno	ARG	Buenos Aires	356993	2007-07-05 22:20:16
84	Santa Fé	ARG	Santa Fé	353063	2001-12-10 07:53:59
85	Avellaneda	ARG	Buenos Aires	353046	2013-01-23 21:16:37
86	Tres de Febrero	ARG	Buenos Aires	352311	2001-01-19 16:16:16
87	Morón	ARG	Buenos Aires	349246	2006-09-19 19:01:30
88	Florencio Varela	ARG	Buenos Aires	315432	2001-09-23 23:16:55
89	San Isidro	ARG	Buenos Aires	306341	2004-08-12 11:08:27
90	Tigre	ARG	Buenos Aires	296226	2011-09-05 02:35:36
91	Malvinas Argentinas	ARG	Buenos Aires	290335	2004-02-01 15:11:43
92	Vicente López	ARG	Buenos Aires	288341	2010-04-02 09:44:22
93	Berazategui	ARG	Buenos Aires	276916	2003-03-11 16:35:18
94	Corrientes	ARG	Corrientes	258103	2003-04-05 21:21:21
95	San Miguel	ARG	Buenos Aires	248700	2012-06-28 14:14:20
96	Bahía Blanca	ARG	Buenos Aires	239810	2010-01-19 11:05:55
97	Esteban Echeverría	ARG	Buenos Aires	235760	2012-08-17 16:40:38
98	Resistencia	ARG	Chaco	229212	2009-04-24 12:01:17
99	José C. Paz	ARG	Buenos Aires	221754	2007-01-22 03:02:31
100	Paraná	ARG	Entre Rios	207041	2007-04-25 12:14:41
101	Godoy Cruz	ARG	Mendoza	206998	2001-10-03 10:38:25
102	Posadas	ARG	Misiones	201273	2006-05-20 18:53:45
103	Guaymallén	ARG	Mendoza	200595	2014-07-01 05:18:13
104	Santiago del Estero	ARG	Santiago del Estero	189947	2008-04-16 10:33:59
105	San Salvador de Jujuy	ARG	Jujuy	178748	2005-03-16 02:06:23
106	Hurlingham	ARG	Buenos Aires	170028	2013-02-08 04:06:50
107	Neuquén	ARG	Neuquén	167296	2009-12-26 22:35:58
108	Ituzaingó	ARG	Buenos Aires	158197	2005-12-17 13:29:13
109	San Fernando	ARG	Buenos Aires	153036	2002-01-04 15:57:33
110	Formosa	ARG	Formosa	147636	2001-07-12 04:23:14
111	Las Heras	ARG	Mendoza	145823	2013-10-08 03:34:42
112	La Rioja	ARG	La Rioja	138117	2001-03-16 08:22:21
113	San Fernando del Valle de Cata	ARG	Catamarca	134935	2003-03-02 16:44:21
114	Río Cuarto	ARG	Córdoba	134355	2004-10-28 23:54:05
115	Comodoro Rivadavia	ARG	Chubut	124104	2007-07-11 07:52:23
116	Mendoza	ARG	Mendoza	123027	2008-07-09 06:28:13
117	San Nicolás de los Arroyos	ARG	Buenos Aires	119302	2005-12-11 15:06:19
118	San Juan	ARG	San Juan	119152	2011-04-25 17:29:37
119	Escobar	ARG	Buenos Aires	116675	2011-10-02 09:34:57
120	Concordia	ARG	Entre Rios	116485	2008-07-24 19:38:55
121	Pilar	ARG	Buenos Aires	113428	2006-11-11 10:05:37
122	San Luis	ARG	San Luis	110136	2010-07-18 04:37:08
123	Ezeiza	ARG	Buenos Aires	99578	2003-06-01 23:46:14
124	San Rafael	ARG	Mendoza	94651	2010-05-01 05:23:49
125	Tandil	ARG	Buenos Aires	91101	2008-10-20 09:49:51
126	Yerevan	ARM	Yerevan	1248700	2012-03-08 20:53:24
127	Gjumri	ARM	irak	211700	2004-06-10 13:03:10
128	Vanadzor	ARM	Lori	172700	2014-03-17 16:32:34
129	Oranjestad	ABW		29034	2001-06-11 22:32:10
130	Sydney	AUS	New South Wales	3276207	2011-11-06 06:49:35
131	Melbourne	AUS	Victoria	2865329	2011-07-02 14:39:14
132	Brisbane	AUS	Queensland	1291117	2006-03-25 21:23:27
133	Perth	AUS	West Australia	1096829	2008-08-14 17:07:49
134	Adelaide	AUS	South Australia	978100	2002-05-01 21:33:12
135	Canberra	AUS	Capital Region	322723	2008-03-03 04:49:28
136	Gold Coast	AUS	Queensland	311932	2005-04-21 15:22:59
137	Newcastle	AUS	New South Wales	270324	2002-03-23 22:55:44
138	Central Coast	AUS	New South Wales	227657	2007-03-23 07:29:09
139	Wollongong	AUS	New South Wales	219761	2006-11-13 17:23:32
140	Hobart	AUS	Tasmania	126118	2006-08-09 18:19:51
141	Geelong	AUS	Victoria	125382	2001-11-23 17:28:52
142	Townsville	AUS	Queensland	109914	2012-02-13 10:20:06
143	Cairns	AUS	Queensland	92273	2012-04-27 15:39:47
144	Baku	AZE	Baki	1787800	2003-12-15 16:35:48
145	Gäncä	AZE	Gäncä	299300	2006-07-09 12:38:39
146	Sumqayit	AZE	Sumqayit	283000	2007-07-04 12:49:39
147	Mingäçevir	AZE	Mingäçevir	93900	2010-05-13 09:49:32
148	Nassau	BHS	New Providence	172000	2008-01-26 18:45:43
149	al-Manama	BHR	al-Manama	148000	2001-06-08 15:01:16
150	Dhaka	BGD	Dhaka	3612850	2003-01-06 11:26:59
151	Chittagong	BGD	Chittagong	1392860	2008-01-19 15:09:59
152	Khulna	BGD	Khulna	663340	2011-07-09 04:30:32
153	Rajshahi	BGD	Rajshahi	294056	2013-11-01 08:23:09
154	Narayanganj	BGD	Dhaka	202134	2003-02-26 20:32:40
155	Rangpur	BGD	Rajshahi	191398	2009-11-24 16:21:59
156	Mymensingh	BGD	Dhaka	188713	2003-05-04 23:14:51
157	Barisal	BGD	Barisal	170232	2004-09-21 13:42:40
158	Tungi	BGD	Dhaka	168702	2013-12-06 01:31:09
159	Jessore	BGD	Khulna	139710	2006-12-28 23:47:53
160	Comilla	BGD	Chittagong	135313	2014-09-04 15:40:21
161	Nawabganj	BGD	Rajshahi	130577	2010-08-28 08:23:01
162	Dinajpur	BGD	Rajshahi	127815	2013-03-13 04:33:18
163	Bogra	BGD	Rajshahi	120170	2004-05-22 10:41:45
164	Sylhet	BGD	Sylhet	117396	2012-08-17 07:57:29
165	Brahmanbaria	BGD	Chittagong	109032	2009-05-10 09:08:51
166	Tangail	BGD	Dhaka	106004	2008-07-07 01:06:52
167	Jamalpur	BGD	Dhaka	103556	2012-06-21 14:27:32
168	Pabna	BGD	Rajshahi	103277	2007-10-19 14:09:38
169	Naogaon	BGD	Rajshahi	101266	2001-09-19 11:32:34
170	Sirajganj	BGD	Rajshahi	99669	2012-03-13 11:56:09
171	Narsinghdi	BGD	Dhaka	98342	2006-02-05 18:38:53
172	Saidpur	BGD	Rajshahi	96777	2014-10-11 13:23:33
173	Gazipur	BGD	Dhaka	96717	2011-12-20 01:57:34
174	Bridgetown	BRB	St Michael	6070	2010-08-27 13:13:15
175	Antwerpen	BEL	Antwerpen	446525	2013-03-15 07:42:17
176	Gent	BEL	East Flanderi	224180	2006-10-19 05:42:32
177	Charleroi	BEL	Hainaut	200827	2009-01-18 21:17:39
178	Liège	BEL	Liège	185639	2002-05-26 01:39:37
179	Bruxelles [Brussel]	BEL	Bryssel	133859	2012-07-07 12:17:52
180	Brugge	BEL	West Flanderi	116246	2012-05-18 19:29:03
181	Schaerbeek	BEL	Bryssel	105692	2009-05-09 23:49:29
182	Namur	BEL	Namur	105419	2001-07-06 21:59:17
183	Mons	BEL	Hainaut	90935	2002-03-01 19:34:49
184	Belize City	BLZ	Belize City	55810	2011-01-09 04:17:46
185	Belmopan	BLZ	Cayo	7105	2003-11-28 06:06:15
186	Cotonou	BEN	Atlantique	536827	2011-09-05 23:32:45
187	Porto-Novo	BEN	Ouémé	194000	2005-09-18 06:31:26
188	Djougou	BEN	Atacora	134099	2002-11-01 17:02:26
189	Parakou	BEN	Borgou	103577	2012-06-05 13:30:20
190	Saint George	BMU	Saint George´s	1800	2012-02-11 19:45:13
191	Hamilton	BMU	Hamilton	1200	2013-02-18 15:27:09
192	Thimphu	BTN	Thimphu	22000	2014-07-18 04:31:22
193	Santa Cruz de la Sierra	BOL	Santa Cruz	935361	2011-09-01 08:29:53
194	La Paz	BOL	La Paz	758141	2004-06-19 22:43:02
195	El Alto	BOL	La Paz	534466	2005-05-19 06:08:13
196	Cochabamba	BOL	Cochabamba	482800	2005-12-15 13:48:58
197	Oruro	BOL	Oruro	223553	2013-11-12 12:44:22
198	Sucre	BOL	Chuquisaca	178426	2001-07-22 03:51:01
199	Potosí	BOL	Potosí	140642	2005-08-17 19:19:37
200	Tarija	BOL	Tarija	125255	2003-10-14 02:03:50
201	Sarajevo	BIH	Federaatio	360000	2002-03-05 04:22:32
202	Banja Luka	BIH	Republika Srpska	143079	2007-10-12 15:27:59
203	Zenica	BIH	Federaatio	96027	2003-08-25 07:32:34
204	Gaborone	BWA	Gaborone	213017	2007-08-07 07:30:24
205	Francistown	BWA	Francistown	101805	2001-01-21 02:27:31
206	São Paulo	BRA	São Paulo	9968485	2008-07-05 03:31:38
207	Rio de Janeiro	BRA	Rio de Janeiro	5598953	2004-02-05 13:44:53
208	Salvador	BRA	Bahia	2302832	2003-04-21 19:35:49
209	Belo Horizonte	BRA	Minas Gerais	2139125	2006-06-28 09:08:48
210	Fortaleza	BRA	Ceará	2097757	2014-04-24 22:50:10
211	Brasília	BRA	Distrito Federal	1969868	2005-08-07 20:09:39
212	Curitiba	BRA	Paraná	1584232	2005-02-13 21:23:45
213	Recife	BRA	Pernambuco	1378087	2004-01-28 22:37:27
214	Porto Alegre	BRA	Rio Grande do Sul	1314032	2014-01-28 23:59:55
215	Manaus	BRA	Amazonas	1255049	2003-01-25 23:10:40
216	Belém	BRA	Pará	1186926	2013-12-08 15:52:05
217	Guarulhos	BRA	São Paulo	1095874	2012-05-27 11:03:36
218	Goiânia	BRA	Goiás	1056330	2004-08-11 21:55:30
219	Campinas	BRA	São Paulo	950043	2012-09-18 01:20:17
220	São Gonçalo	BRA	Rio de Janeiro	869254	2008-07-27 23:44:19
221	Nova Iguaçu	BRA	Rio de Janeiro	862225	2011-09-22 06:28:22
222	São Luís	BRA	Maranhão	837588	2006-06-23 22:23:37
223	Maceió	BRA	Alagoas	786288	2011-07-27 08:08:23
224	Duque de Caxias	BRA	Rio de Janeiro	746758	2007-10-18 01:44:35
225	São Bernardo do Campo	BRA	São Paulo	723132	2012-06-24 19:19:03
226	Teresina	BRA	Piauí	691942	2004-01-06 01:20:37
227	Natal	BRA	Rio Grande do Norte	688955	2013-03-07 02:37:12
228	Osasco	BRA	São Paulo	659604	2011-09-19 02:31:24
229	Campo Grande	BRA	Mato Grosso do Sul	649593	2002-04-22 16:14:57
230	Santo André	BRA	São Paulo	630073	2011-07-06 01:14:12
231	João Pessoa	BRA	Paraíba	584029	2002-07-16 10:53:38
232	Jaboatão dos Guararapes	BRA	Pernambuco	558680	2013-02-07 06:34:07
233	Contagem	BRA	Minas Gerais	520801	2010-12-26 02:40:23
234	São José dos Campos	BRA	São Paulo	515553	2006-11-17 07:52:19
235	Uberlândia	BRA	Minas Gerais	487222	2009-06-14 12:58:12
236	Feira de Santana	BRA	Bahia	479992	2001-02-20 20:38:39
237	Ribeirão Preto	BRA	São Paulo	473276	2010-11-19 19:05:50
238	Sorocaba	BRA	São Paulo	466823	2008-06-23 07:23:16
239	Niterói	BRA	Rio de Janeiro	459884	2012-01-25 02:21:35
240	Cuiabá	BRA	Mato Grosso	453813	2007-09-01 03:56:37
241	Juiz de Fora	BRA	Minas Gerais	450288	2011-07-28 17:30:16
242	Aracaju	BRA	Sergipe	445555	2014-06-10 19:35:02
243	São João de Meriti	BRA	Rio de Janeiro	440052	2007-08-03 10:56:09
244	Londrina	BRA	Paraná	432257	2008-04-10 19:28:11
245	Joinville	BRA	Santa Catarina	428011	2007-01-25 17:01:50
246	Belford Roxo	BRA	Rio de Janeiro	425194	2008-11-15 21:34:33
247	Santos	BRA	São Paulo	408748	2014-01-21 20:03:11
248	Ananindeua	BRA	Pará	400940	2004-08-14 09:05:33
249	Campos dos Goytacazes	BRA	Rio de Janeiro	398418	2014-11-28 12:06:45
250	Mauá	BRA	São Paulo	375055	2014-05-11 03:30:02
251	Carapicuíba	BRA	São Paulo	357552	2009-11-05 04:20:23
252	Olinda	BRA	Pernambuco	354732	2008-01-25 23:29:33
253	Campina Grande	BRA	Paraíba	352497	2002-12-21 03:45:34
254	São José do Rio Preto	BRA	São Paulo	351944	2007-03-12 22:38:38
255	Caxias do Sul	BRA	Rio Grande do Sul	349581	2013-03-21 22:51:53
256	Moji das Cruzes	BRA	São Paulo	339194	2003-10-27 15:49:51
257	Diadema	BRA	São Paulo	335078	2008-11-02 12:01:27
258	Aparecida de Goiânia	BRA	Goiás	324662	2012-11-24 01:12:44
259	Piracicaba	BRA	São Paulo	319104	2005-11-12 06:08:54
260	Cariacica	BRA	Espírito Santo	319033	2010-04-23 15:52:15
261	Vila Velha	BRA	Espírito Santo	318758	2013-02-25 02:46:55
262	Pelotas	BRA	Rio Grande do Sul	315415	2006-07-14 20:58:17
263	Bauru	BRA	São Paulo	313670	2007-10-23 18:33:14
264	Porto Velho	BRA	Rondônia	309750	2010-07-26 13:40:13
265	Serra	BRA	Espírito Santo	302666	2008-08-12 21:33:38
266	Betim	BRA	Minas Gerais	302108	2011-08-09 19:53:56
267	Jundíaí	BRA	São Paulo	296127	2012-05-28 11:32:20
268	Canoas	BRA	Rio Grande do Sul	294125	2012-09-15 16:04:18
269	Franca	BRA	São Paulo	290139	2011-02-18 01:57:35
270	São Vicente	BRA	São Paulo	286848	2004-08-18 15:17:22
271	Maringá	BRA	Paraná	286461	2008-08-03 19:18:35
272	Montes Claros	BRA	Minas Gerais	286058	2013-01-24 06:36:52
273	Anápolis	BRA	Goiás	282197	2003-06-06 12:36:32
274	Florianópolis	BRA	Santa Catarina	281928	2008-05-24 15:57:23
275	Petrópolis	BRA	Rio de Janeiro	279183	2012-06-18 12:09:12
276	Itaquaquecetuba	BRA	São Paulo	270874	2006-10-15 03:37:48
277	Vitória	BRA	Espírito Santo	270626	2001-10-24 14:26:08
278	Ponta Grossa	BRA	Paraná	268013	2002-04-22 10:52:28
279	Rio Branco	BRA	Acre	259537	2012-11-07 23:48:41
280	Foz do Iguaçu	BRA	Paraná	259425	2008-10-14 02:33:58
281	Macapá	BRA	Amapá	256033	2009-06-23 02:57:39
282	Ilhéus	BRA	Bahia	254970	2009-09-26 20:59:09
283	Vitória da Conquista	BRA	Bahia	253587	2006-12-27 08:19:58
284	Uberaba	BRA	Minas Gerais	249225	2010-12-20 14:56:45
285	Paulista	BRA	Pernambuco	248473	2002-12-18 22:36:45
286	Limeira	BRA	São Paulo	245497	2004-01-27 04:09:18
287	Blumenau	BRA	Santa Catarina	244379	2013-06-28 14:28:07
288	Caruaru	BRA	Pernambuco	244247	2010-11-02 11:29:41
289	Santarém	BRA	Pará	241771	2011-09-07 15:05:57
290	Volta Redonda	BRA	Rio de Janeiro	240315	2013-07-01 07:41:22
291	Novo Hamburgo	BRA	Rio Grande do Sul	239940	2013-01-12 02:19:12
292	Caucaia	BRA	Ceará	238738	2009-01-03 22:33:30
293	Santa Maria	BRA	Rio Grande do Sul	238473	2001-10-01 23:29:16
294	Cascavel	BRA	Paraná	237510	2003-03-20 23:38:34
295	Guarujá	BRA	São Paulo	237206	2007-08-25 20:40:41
296	Ribeirão das Neves	BRA	Minas Gerais	232685	2005-12-20 18:46:38
297	Governador Valadares	BRA	Minas Gerais	231724	2013-11-25 16:23:52
298	Taubaté	BRA	São Paulo	229130	2008-06-15 21:36:54
299	Imperatriz	BRA	Maranhão	224564	2009-07-12 21:21:52
300	Gravataí	BRA	Rio Grande do Sul	223011	2014-04-23 16:15:28
301	Embu	BRA	São Paulo	222223	2004-05-09 23:04:27
302	Mossoró	BRA	Rio Grande do Norte	214901	2007-11-25 10:56:53
303	Várzea Grande	BRA	Mato Grosso	214435	2006-05-16 15:40:58
304	Petrolina	BRA	Pernambuco	210540	2007-06-26 11:43:02
305	Barueri	BRA	São Paulo	208426	2007-04-09 04:31:58
306	Viamão	BRA	Rio Grande do Sul	207557	2014-11-11 05:52:56
307	Ipatinga	BRA	Minas Gerais	206338	2002-12-07 03:09:26
308	Juazeiro	BRA	Bahia	201073	2004-06-03 19:19:46
309	Juazeiro do Norte	BRA	Ceará	199636	2006-08-02 16:55:48
310	Taboão da Serra	BRA	São Paulo	197550	2001-02-17 20:39:34
311	São José dos Pinhais	BRA	Paraná	196884	2008-08-05 09:18:13
312	Magé	BRA	Rio de Janeiro	196147	2008-07-11 23:12:10
313	Suzano	BRA	São Paulo	195434	2001-12-16 12:59:18
314	São Leopoldo	BRA	Rio Grande do Sul	189258	2003-03-16 18:41:22
315	Marília	BRA	São Paulo	188691	2007-05-18 15:16:54
316	São Carlos	BRA	São Paulo	187122	2012-11-08 01:54:58
317	Sumaré	BRA	São Paulo	186205	2010-04-21 18:52:48
318	Presidente Prudente	BRA	São Paulo	185340	2006-01-17 03:20:52
319	Divinópolis	BRA	Minas Gerais	185047	2004-04-02 23:57:17
320	Sete Lagoas	BRA	Minas Gerais	182984	2007-01-20 12:45:08
321	Rio Grande	BRA	Rio Grande do Sul	182222	2007-11-25 09:10:42
322	Itabuna	BRA	Bahia	182148	2004-04-25 08:26:28
323	Jequié	BRA	Bahia	179128	2013-04-13 12:14:19
324	Arapiraca	BRA	Alagoas	178988	2011-07-04 07:23:37
325	Colombo	BRA	Paraná	177764	2007-02-08 20:23:14
326	Americana	BRA	São Paulo	177409	2003-12-12 16:31:56
327	Alvorada	BRA	Rio Grande do Sul	175574	2011-08-17 23:14:54
328	Araraquara	BRA	São Paulo	174381	2014-01-26 19:19:33
329	Itaboraí	BRA	Rio de Janeiro	173977	2014-05-22 05:34:24
330	Santa Bárbara d´Oeste	BRA	São Paulo	171657	2011-02-16 01:04:43
331	Nova Friburgo	BRA	Rio de Janeiro	170697	2002-09-19 03:22:40
332	Jacareí	BRA	São Paulo	170356	2001-08-04 17:28:28
333	Araçatuba	BRA	São Paulo	169303	2011-12-11 16:32:07
334	Barra Mansa	BRA	Rio de Janeiro	168953	2012-10-08 13:11:40
335	Praia Grande	BRA	São Paulo	168434	2003-06-28 14:13:23
336	Marabá	BRA	Pará	167795	2004-07-13 15:39:22
337	Criciúma	BRA	Santa Catarina	167661	2008-09-13 17:13:15
338	Boa Vista	BRA	Roraima	167185	2008-07-13 05:25:04
339	Passo Fundo	BRA	Rio Grande do Sul	166343	2008-06-06 01:09:07
340	Dourados	BRA	Mato Grosso do Sul	164716	2010-06-28 05:30:22
341	Santa Luzia	BRA	Minas Gerais	164704	2001-11-11 14:15:21
342	Rio Claro	BRA	São Paulo	163551	2005-06-26 04:15:18
343	Maracanaú	BRA	Ceará	162022	2008-12-24 10:57:39
344	Guarapuava	BRA	Paraná	160510	2006-04-23 02:26:33
345	Rondonópolis	BRA	Mato Grosso	155115	2002-11-15 21:16:16
346	São José	BRA	Santa Catarina	155105	2010-03-15 12:38:06
347	Cachoeiro de Itapemirim	BRA	Espírito Santo	155024	2003-03-24 08:58:16
348	Nilópolis	BRA	Rio de Janeiro	153383	2013-07-25 11:11:51
349	Itapevi	BRA	São Paulo	150664	2014-03-13 15:06:20
350	Cabo de Santo Agostinho	BRA	Pernambuco	149964	2004-11-01 03:50:34
351	Camaçari	BRA	Bahia	149146	2008-04-22 06:43:35
352	Sobral	BRA	Ceará	146005	2007-06-20 21:15:10
353	Itajaí	BRA	Santa Catarina	145197	2010-12-27 02:43:47
354	Chapecó	BRA	Santa Catarina	144158	2013-04-26 11:48:23
355	Cotia	BRA	São Paulo	140042	2008-12-12 22:52:25
356	Lages	BRA	Santa Catarina	139570	2008-05-06 01:25:40
357	Ferraz de Vasconcelos	BRA	São Paulo	139283	2007-02-21 01:05:35
358	Indaiatuba	BRA	São Paulo	135968	2003-12-21 21:39:06
359	Hortolândia	BRA	São Paulo	135755	2009-12-03 05:50:16
360	Caxias	BRA	Maranhão	133980	2001-04-04 15:23:22
361	São Caetano do Sul	BRA	São Paulo	133321	2003-05-22 05:59:27
362	Itu	BRA	São Paulo	132736	2012-10-18 11:18:02
363	Nossa Senhora do Socorro	BRA	Sergipe	131351	2004-03-24 16:10:56
364	Parnaíba	BRA	Piauí	129756	2007-11-22 23:37:52
365	Poços de Caldas	BRA	Minas Gerais	129683	2010-08-12 10:36:34
366	Teresópolis	BRA	Rio de Janeiro	128079	2010-12-16 03:47:22
367	Barreiras	BRA	Bahia	127801	2001-10-10 17:14:37
368	Castanhal	BRA	Pará	127634	2007-04-12 22:40:42
369	Alagoinhas	BRA	Bahia	126820	2012-08-06 14:11:22
370	Itapecerica da Serra	BRA	São Paulo	126672	2014-08-19 23:58:33
371	Uruguaiana	BRA	Rio Grande do Sul	126305	2010-09-04 13:39:33
372	Paranaguá	BRA	Paraná	126076	2003-04-19 09:45:01
373	Ibirité	BRA	Minas Gerais	125982	2014-05-21 14:47:31
374	Timon	BRA	Maranhão	125812	2004-02-28 23:36:10
375	Luziânia	BRA	Goiás	125597	2013-10-19 09:02:35
376	Macaé	BRA	Rio de Janeiro	125597	2005-09-23 05:01:52
377	Teófilo Otoni	BRA	Minas Gerais	124489	2010-10-12 18:49:47
378	Moji-Guaçu	BRA	São Paulo	123782	2014-02-07 07:42:28
379	Palmas	BRA	Tocantins	121919	2006-08-06 19:01:20
380	Pindamonhangaba	BRA	São Paulo	121904	2009-12-15 07:30:38
381	Francisco Morato	BRA	São Paulo	121197	2008-06-28 19:51:31
382	Bagé	BRA	Rio Grande do Sul	120793	2012-03-14 11:23:34
383	Sapucaia do Sul	BRA	Rio Grande do Sul	120217	2012-10-25 11:40:51
384	Cabo Frio	BRA	Rio de Janeiro	119503	2009-08-13 12:42:49
385	Itapetininga	BRA	São Paulo	119391	2011-03-01 22:31:16
386	Patos de Minas	BRA	Minas Gerais	119262	2007-08-19 08:07:17
387	Camaragibe	BRA	Pernambuco	118968	2012-12-20 05:34:25
388	Bragança Paulista	BRA	São Paulo	116929	2010-04-10 23:15:07
389	Queimados	BRA	Rio de Janeiro	115020	2001-11-17 23:32:39
390	Araguaína	BRA	Tocantins	114948	2011-07-10 03:09:43
391	Garanhuns	BRA	Pernambuco	114603	2005-05-11 07:21:56
392	Vitória de Santo Antão	BRA	Pernambuco	113595	2006-09-08 22:25:31
393	Santa Rita	BRA	Paraíba	113135	2005-05-09 06:42:06
394	Barbacena	BRA	Minas Gerais	113079	2008-09-05 19:51:17
395	Abaetetuba	BRA	Pará	111258	2013-12-15 21:05:04
396	Jaú	BRA	São Paulo	109965	2003-12-21 05:01:32
397	Lauro de Freitas	BRA	Bahia	109236	2013-11-02 17:04:09
398	Franco da Rocha	BRA	São Paulo	108964	2011-11-18 20:18:17
399	Teixeira de Freitas	BRA	Bahia	108441	2005-01-14 06:59:49
400	Varginha	BRA	Minas Gerais	108314	2006-02-14 10:17:08
401	Ribeirão Pires	BRA	São Paulo	108121	2013-05-12 23:19:12
402	Sabará	BRA	Minas Gerais	107781	2001-09-02 13:08:15
403	Catanduva	BRA	São Paulo	107761	2013-07-23 09:53:03
404	Rio Verde	BRA	Goiás	107755	2006-08-23 08:42:44
405	Botucatu	BRA	São Paulo	107663	2014-01-08 13:33:59
406	Colatina	BRA	Espírito Santo	107354	2005-11-05 05:42:03
407	Santa Cruz do Sul	BRA	Rio Grande do Sul	106734	2012-01-19 16:26:25
408	Linhares	BRA	Espírito Santo	106278	2004-06-05 09:44:08
409	Apucarana	BRA	Paraná	105114	2008-08-18 07:37:01
410	Barretos	BRA	São Paulo	104156	2007-06-16 03:01:49
411	Guaratinguetá	BRA	São Paulo	103433	2009-07-19 21:56:23
412	Cachoeirinha	BRA	Rio Grande do Sul	103240	2001-03-04 01:59:57
413	Codó	BRA	Maranhão	103153	2006-10-17 13:07:33
414	Jaraguá do Sul	BRA	Santa Catarina	102580	2013-06-08 13:23:52
415	Cubatão	BRA	São Paulo	102372	2002-05-13 10:18:41
416	Itabira	BRA	Minas Gerais	102217	2001-01-17 05:16:16
417	Itaituba	BRA	Pará	101320	2011-01-13 07:01:03
418	Araras	BRA	São Paulo	101046	2005-12-22 07:42:13
419	Resende	BRA	Rio de Janeiro	100627	2007-02-09 21:01:06
420	Atibaia	BRA	São Paulo	100356	2002-02-25 01:33:30
421	Pouso Alegre	BRA	Minas Gerais	100028	2005-08-19 09:23:31
422	Toledo	BRA	Paraná	99387	2003-08-17 08:20:44
423	Crato	BRA	Ceará	98965	2007-12-28 07:33:44
424	Passos	BRA	Minas Gerais	98570	2005-12-03 08:50:31
425	Araguari	BRA	Minas Gerais	98399	2004-05-11 22:40:36
426	São José de Ribamar	BRA	Maranhão	98318	2003-06-09 09:51:07
427	Pinhais	BRA	Paraná	98198	2010-02-23 07:20:31
428	Sertãozinho	BRA	São Paulo	98140	2005-09-05 17:40:27
429	Conselheiro Lafaiete	BRA	Minas Gerais	97507	2002-09-27 11:44:35
430	Paulo Afonso	BRA	Bahia	97291	2014-10-18 06:25:55
431	Angra dos Reis	BRA	Rio de Janeiro	96864	2014-03-05 22:38:05
432	Eunápolis	BRA	Bahia	96610	2007-06-06 11:38:19
433	Salto	BRA	São Paulo	96348	2009-04-02 06:44:29
434	Ourinhos	BRA	São Paulo	96291	2010-08-22 08:41:13
435	Parnamirim	BRA	Rio Grande do Norte	96210	2002-09-03 22:38:14
436	Jacobina	BRA	Bahia	96131	2011-11-15 02:05:11
437	Coronel Fabriciano	BRA	Minas Gerais	95933	2006-04-18 06:28:13
438	Birigui	BRA	São Paulo	94685	2013-12-07 05:39:26
439	Tatuí	BRA	São Paulo	93897	2006-06-15 16:32:33
440	Ji-Paraná	BRA	Rondônia	93346	2009-10-13 07:54:47
441	Bacabal	BRA	Maranhão	93121	2006-01-24 06:36:49
442	Cametá	BRA	Pará	92779	2003-02-24 07:11:35
443	Guaíba	BRA	Rio Grande do Sul	92224	2004-11-28 07:13:01
444	São Lourenço da Mata	BRA	Pernambuco	91999	2014-11-03 19:54:44
445	Santana do Livramento	BRA	Rio Grande do Sul	91779	2004-05-17 06:59:06
446	Votorantim	BRA	São Paulo	91777	2007-03-28 14:21:02
447	Campo Largo	BRA	Paraná	91203	2013-11-21 09:40:44
448	Patos	BRA	Paraíba	90519	2001-12-05 12:37:21
449	Ituiutaba	BRA	Minas Gerais	90507	2014-09-22 04:34:50
450	Corumbá	BRA	Mato Grosso do Sul	90111	2004-04-26 17:21:30
451	Palhoça	BRA	Santa Catarina	89465	2008-09-11 23:28:11
452	Barra do Piraí	BRA	Rio de Janeiro	89388	2008-05-22 22:46:44
453	Bento Gonçalves	BRA	Rio Grande do Sul	89254	2011-03-04 23:50:31
454	Poá	BRA	São Paulo	89236	2007-06-02 19:54:30
455	Águas Lindas de Goiás	BRA	Goiás	89200	2010-06-01 02:18:30
456	London	GBR	England	7285000	2008-10-16 09:07:18
457	Birmingham	GBR	England	1013000	2001-11-11 02:37:51
458	Glasgow	GBR	Scotland	619680	2005-10-15 23:26:05
459	Liverpool	GBR	England	461000	2001-03-20 08:41:27
460	Edinburgh	GBR	Scotland	450180	2008-10-10 13:51:43
461	Sheffield	GBR	England	431607	2008-03-03 07:33:10
462	Manchester	GBR	England	430000	2010-02-10 23:10:04
463	Leeds	GBR	England	424194	2014-07-08 11:57:08
464	Bristol	GBR	England	402000	2012-02-05 14:02:36
465	Cardiff	GBR	Wales	321000	2003-04-18 11:43:14
466	Coventry	GBR	England	304000	2003-04-15 01:12:51
467	Leicester	GBR	England	294000	2006-09-25 16:31:42
468	Bradford	GBR	England	289376	2003-02-20 09:37:42
469	Belfast	GBR	North Ireland	287500	2004-11-06 12:06:40
470	Nottingham	GBR	England	287000	2011-07-14 10:46:05
471	Kingston upon Hull	GBR	England	262000	2001-10-13 01:42:50
472	Plymouth	GBR	England	253000	2009-08-27 08:18:24
473	Stoke-on-Trent	GBR	England	252000	2011-01-27 12:47:58
474	Wolverhampton	GBR	England	242000	2013-10-02 06:17:18
475	Derby	GBR	England	236000	2013-07-18 03:56:06
476	Swansea	GBR	Wales	230000	2004-09-25 18:36:24
477	Southampton	GBR	England	216000	2010-12-13 23:23:50
478	Aberdeen	GBR	Scotland	213070	2003-01-12 19:43:57
479	Northampton	GBR	England	196000	2002-12-09 21:20:38
480	Dudley	GBR	England	192171	2008-05-27 15:32:40
481	Portsmouth	GBR	England	190000	2013-09-25 18:56:14
482	Newcastle upon Tyne	GBR	England	189150	2005-11-20 17:33:50
483	Sunderland	GBR	England	183310	2013-08-02 04:21:06
484	Luton	GBR	England	183000	2011-10-04 02:51:12
485	Swindon	GBR	England	180000	2006-05-26 06:02:39
486	Southend-on-Sea	GBR	England	176000	2004-09-24 21:37:18
487	Walsall	GBR	England	174739	2005-02-02 11:41:11
488	Bournemouth	GBR	England	162000	2010-08-17 19:07:40
489	Peterborough	GBR	England	156000	2007-12-09 20:38:35
490	Brighton	GBR	England	156124	2011-06-10 16:07:31
491	Blackpool	GBR	England	151000	2001-11-28 06:27:33
492	Dundee	GBR	Scotland	146690	2010-06-24 07:30:58
493	West Bromwich	GBR	England	146386	2011-08-22 23:04:31
494	Reading	GBR	England	148000	2011-04-21 06:36:39
495	Oldbury/Smethwick (Warley)	GBR	England	145542	2012-06-06 22:20:55
496	Middlesbrough	GBR	England	145000	2008-10-08 21:23:40
497	Huddersfield	GBR	England	143726	2002-07-26 23:05:09
498	Oxford	GBR	England	144000	2013-09-15 10:58:52
499	Poole	GBR	England	141000	2012-10-09 23:10:07
500	Bolton	GBR	England	139020	2010-07-07 01:01:22
501	Blackburn	GBR	England	140000	2001-07-18 11:35:36
502	Newport	GBR	Wales	139000	2007-02-03 23:53:22
503	Preston	GBR	England	135000	2008-11-27 16:16:58
504	Stockport	GBR	England	132813	2013-09-14 04:01:34
505	Norwich	GBR	England	124000	2002-10-05 09:04:15
506	Rotherham	GBR	England	121380	2004-10-20 01:08:31
507	Cambridge	GBR	England	121000	2005-01-01 16:47:10
508	Watford	GBR	England	113080	2009-04-02 10:28:52
509	Ipswich	GBR	England	114000	2006-03-21 08:05:09
510	Slough	GBR	England	112000	2007-03-28 17:30:24
511	Exeter	GBR	England	111000	2014-12-01 08:54:11
512	Cheltenham	GBR	England	106000	2003-05-05 19:23:58
513	Gloucester	GBR	England	107000	2013-09-17 20:33:34
514	Saint Helens	GBR	England	106293	2013-05-12 11:03:18
515	Sutton Coldfield	GBR	England	106001	2002-01-22 12:33:24
516	York	GBR	England	104425	2010-02-01 21:22:52
517	Oldham	GBR	England	103931	2004-05-07 13:53:39
518	Basildon	GBR	England	100924	2012-12-02 03:12:08
519	Worthing	GBR	England	100000	2005-09-17 17:26:57
520	Chelmsford	GBR	England	97451	2003-03-07 08:18:08
521	Colchester	GBR	England	96063	2006-04-13 23:44:05
522	Crawley	GBR	England	97000	2011-09-21 03:35:41
523	Gillingham	GBR	England	92000	2010-03-18 17:11:59
524	Solihull	GBR	England	94531	2005-04-06 10:01:58
525	Rochdale	GBR	England	94313	2004-09-17 07:47:39
526	Birkenhead	GBR	England	93087	2011-11-01 07:03:29
527	Worcester	GBR	England	95000	2011-11-19 17:01:46
528	Hartlepool	GBR	England	92000	2009-08-22 12:53:37
529	Halifax	GBR	England	91069	2001-09-07 15:18:39
530	Woking/Byfleet	GBR	England	92000	2006-07-07 14:23:33
531	Southport	GBR	England	90959	2012-10-16 10:12:25
532	Maidstone	GBR	England	90878	2012-10-20 19:54:26
533	Eastbourne	GBR	England	90000	2012-02-02 18:44:20
534	Grimsby	GBR	England	89000	2003-10-17 11:54:52
535	Saint Helier	GBR	Jersey	27523	2004-09-02 09:04:45
536	Douglas	GBR		23487	2009-09-25 01:23:15
537	Road Town	VGB	Tortola	8000	2004-11-17 01:30:07
538	Bandar Seri Begawan	BRN	Brunei and Muara	21484	2012-06-24 23:43:34
539	Sofija	BGR	Grad Sofija	1122302	2012-06-14 17:19:19
540	Plovdiv	BGR	Plovdiv	342584	2001-03-19 14:39:10
541	Varna	BGR	Varna	299801	2002-12-23 22:49:03
542	Burgas	BGR	Burgas	195255	2012-09-09 16:40:47
543	Ruse	BGR	Ruse	166467	2010-07-15 10:34:32
544	Stara Zagora	BGR	Haskovo	147939	2003-06-12 17:23:19
545	Pleven	BGR	Lovec	121952	2008-01-26 14:09:53
546	Sliven	BGR	Burgas	105530	2003-03-18 05:27:53
547	Dobric	BGR	Varna	100399	2008-11-27 15:51:03
548	umen	BGR	Varna	94686	2005-10-13 13:47:23
549	Ouagadougou	BFA	Kadiogo	824000	2006-09-16 09:27:45
550	Bobo-Dioulasso	BFA	Houet	300000	2001-07-06 06:54:10
551	Koudougou	BFA	Boulkiemdé	105000	2008-09-06 06:14:32
552	Bujumbura	BDI	Bujumbura	300000	2001-04-25 16:01:30
553	George Town	CYM	Grand Cayman	19600	2003-06-09 09:42:37
554	Santiago de Chile	CHL	Santiago	4703954	2010-01-28 07:37:49
555	Puente Alto	CHL	Santiago	386236	2014-09-23 23:27:58
556	Viña del Mar	CHL	Valparaíso	312493	2010-06-23 04:48:49
557	Valparaíso	CHL	Valparaíso	293800	2001-05-11 16:26:03
558	Talcahuano	CHL	Bíobío	277752	2013-11-15 19:02:55
559	Antofagasta	CHL	Antofagasta	251429	2014-07-06 19:17:40
560	San Bernardo	CHL	Santiago	241910	2002-10-20 21:42:45
561	Temuco	CHL	La Araucanía	233041	2003-06-17 11:33:37
562	Concepción	CHL	Bíobío	217664	2002-12-24 09:44:56
563	Rancagua	CHL	O´Higgins	212977	2012-08-16 18:51:17
564	Arica	CHL	Tarapacá	189036	2006-03-06 02:19:35
565	Talca	CHL	Maule	187557	2003-12-13 22:20:32
566	Chillán	CHL	Bíobío	178182	2004-05-06 08:57:12
567	Iquique	CHL	Tarapacá	177892	2005-06-28 15:46:57
568	Los Angeles	CHL	Bíobío	158215	2008-10-23 08:51:45
569	Puerto Montt	CHL	Los Lagos	152194	2005-02-22 11:56:10
570	Coquimbo	CHL	Coquimbo	143353	2007-11-23 19:18:04
571	Osorno	CHL	Los Lagos	141468	2003-11-12 03:14:08
572	La Serena	CHL	Coquimbo	137409	2009-02-01 03:03:39
573	Calama	CHL	Antofagasta	137265	2012-11-20 13:36:52
574	Valdivia	CHL	Los Lagos	133106	2009-05-11 16:07:44
575	Punta Arenas	CHL	Magallanes	125631	2004-10-25 21:21:49
576	Copiapó	CHL	Atacama	120128	2010-05-02 02:04:48
577	Quilpué	CHL	Valparaíso	118857	2003-10-15 15:18:39
578	Curicó	CHL	Maule	115766	2007-10-07 18:51:16
579	Ovalle	CHL	Coquimbo	94854	2013-01-22 10:27:29
580	Coronel	CHL	Bíobío	93061	2003-02-15 21:47:43
581	San Pedro de la Paz	CHL	Bíobío	91684	2005-09-09 04:44:12
582	Melipilla	CHL	Santiago	91056	2014-09-10 21:16:42
583	Avarua	COK	Rarotonga	11900	2009-01-19 17:44:31
584	San José	CRI	San José	339131	2013-12-07 09:17:13
585	Djibouti	DJI	Djibouti	383000	2002-10-15 13:46:28
586	Roseau	DMA	St George	16243	2011-11-19 09:43:57
587	Santo Domingo de Guzmán	DOM	Distrito Nacional	1609966	2002-10-11 18:14:29
588	Santiago de los Caballeros	DOM	Santiago	365463	2011-08-15 21:03:13
589	La Romana	DOM	La Romana	140204	2012-03-28 03:46:26
590	San Pedro de Macorís	DOM	San Pedro de Macorís	124735	2003-08-08 11:02:48
591	San Francisco de Macorís	DOM	Duarte	108485	2002-08-10 04:23:51
592	San Felipe de Puerto Plata	DOM	Puerto Plata	89423	2010-05-28 06:42:50
593	Guayaquil	ECU	Guayas	2070040	2013-05-24 20:15:14
594	Quito	ECU	Pichincha	1573458	2012-11-09 16:50:53
595	Cuenca	ECU	Azuay	270353	2005-12-09 22:11:18
596	Machala	ECU	El Oro	210368	2012-03-01 15:58:04
597	Santo Domingo de los Colorados	ECU	Pichincha	202111	2007-05-01 16:50:06
598	Portoviejo	ECU	Manabí	176413	2010-09-02 21:09:09
599	Ambato	ECU	Tungurahua	169612	2010-09-22 02:22:30
600	Manta	ECU	Manabí	164739	2013-08-11 08:41:12
601	Duran [Eloy Alfaro]	ECU	Guayas	152514	2005-09-18 03:08:08
602	Ibarra	ECU	Imbabura	130643	2012-04-25 17:16:10
603	Quevedo	ECU	Los Ríos	129631	2010-08-04 06:17:36
604	Milagro	ECU	Guayas	124177	2009-09-06 03:28:15
605	Loja	ECU	Loja	123875	2002-02-19 18:17:06
606	Ríobamba	ECU	Chimborazo	123163	2008-11-14 03:05:47
607	Esmeraldas	ECU	Esmeraldas	123045	2012-07-15 10:33:55
608	Cairo	EGY	Kairo	6789479	2009-07-14 23:33:27
609	Alexandria	EGY	Aleksandria	3328196	2008-08-01 09:26:11
610	Giza	EGY	Giza	2221868	2001-10-18 17:01:17
611	Shubra al-Khayma	EGY	al-Qalyubiya	870716	2008-06-14 01:52:43
612	Port Said	EGY	Port Said	469533	2012-02-12 20:19:31
613	Suez	EGY	Suez	417610	2005-12-01 23:48:37
614	al-Mahallat al-Kubra	EGY	al-Gharbiya	395402	2014-02-14 02:16:26
615	Tanta	EGY	al-Gharbiya	371010	2006-11-06 12:51:01
616	al-Mansura	EGY	al-Daqahliya	369621	2002-11-05 14:26:17
617	Luxor	EGY	Luxor	360503	2014-10-11 22:33:55
618	Asyut	EGY	Asyut	343498	2010-05-15 21:02:56
619	Bahtim	EGY	al-Qalyubiya	275807	2006-05-05 16:20:27
620	Zagazig	EGY	al-Sharqiya	267351	2005-01-13 18:08:50
621	al-Faiyum	EGY	al-Faiyum	260964	2007-05-19 11:54:16
622	Ismailia	EGY	Ismailia	254477	2002-11-16 21:11:48
623	Kafr al-Dawwar	EGY	al-Buhayra	231978	2008-07-08 22:22:46
624	Assuan	EGY	Assuan	219017	2014-05-28 08:47:10
625	Damanhur	EGY	al-Buhayra	212203	2006-11-05 07:33:47
626	al-Minya	EGY	al-Minya	201360	2011-06-05 20:23:42
627	Bani Suwayf	EGY	Bani Suwayf	172032	2014-06-26 08:13:16
628	Qina	EGY	Qina	171275	2005-01-06 09:41:16
629	Sawhaj	EGY	Sawhaj	170125	2006-02-27 17:52:48
630	Shibin al-Kawm	EGY	al-Minufiya	159909	2007-05-15 23:16:29
631	Bulaq al-Dakrur	EGY	Giza	148787	2008-10-17 21:50:39
632	Banha	EGY	al-Qalyubiya	145792	2001-01-26 12:35:45
633	Warraq al-Arab	EGY	Giza	127108	2013-07-05 20:22:07
634	Kafr al-Shaykh	EGY	Kafr al-Shaykh	124819	2001-09-13 21:04:40
635	Mallawi	EGY	al-Minya	119283	2011-10-28 21:34:42
636	Bilbays	EGY	al-Sharqiya	113608	2013-02-06 16:18:18
637	Mit Ghamr	EGY	al-Daqahliya	101801	2007-06-09 15:56:02
638	al-Arish	EGY	Shamal Sina	100447	2009-12-17 12:29:48
639	Talkha	EGY	al-Daqahliya	97700	2011-08-23 22:43:01
640	Qalyub	EGY	al-Qalyubiya	97200	2003-06-19 17:54:49
641	Jirja	EGY	Sawhaj	95400	2001-12-21 10:33:21
642	Idfu	EGY	Qina	94200	2005-12-19 17:34:33
643	al-Hawamidiya	EGY	Giza	91700	2005-10-12 04:38:54
644	Disuq	EGY	Kafr al-Shaykh	91300	2007-09-26 15:41:39
645	San Salvador	SLV	San Salvador	415346	2005-11-23 07:58:54
646	Santa Ana	SLV	Santa Ana	139389	2009-11-16 09:19:43
647	Mejicanos	SLV	San Salvador	138800	2011-11-27 17:28:57
648	Soyapango	SLV	San Salvador	129800	2012-08-12 16:36:54
649	San Miguel	SLV	San Miguel	127696	2010-09-14 01:26:57
650	Nueva San Salvador	SLV	La Libertad	98400	2009-08-25 21:51:15
651	Apopa	SLV	San Salvador	88800	2009-02-08 17:42:53
652	Asmara	ERI	Maekel	431000	2007-05-03 02:27:57
653	Madrid	ESP	Madrid	2879052	2001-06-01 23:40:04
654	Barcelona	ESP	Katalonia	1503451	2013-10-24 07:21:36
655	Valencia	ESP	Valencia	739412	2003-03-11 20:20:26
656	Sevilla	ESP	Andalusia	701927	2011-02-14 06:43:12
657	Zaragoza	ESP	Aragonia	603367	2002-11-09 19:11:16
658	Málaga	ESP	Andalusia	530553	2010-11-16 21:20:56
659	Bilbao	ESP	Baskimaa	357589	2008-11-28 15:13:49
660	Las Palmas de Gran Canaria	ESP	Canary Islands	354757	2013-06-19 05:09:50
661	Murcia	ESP	Murcia	353504	2009-06-06 22:20:11
662	Palma de Mallorca	ESP	Balears	326993	2012-08-25 02:21:17
663	Valladolid	ESP	Castilla and León	319998	2003-01-17 19:09:05
664	Córdoba	ESP	Andalusia	311708	2001-09-03 08:18:30
665	Vigo	ESP	Galicia	283670	2001-03-17 10:23:08
666	Alicante [Alacant]	ESP	Valencia	272432	2014-04-08 23:16:46
667	Gijón	ESP	Asturia	267980	2002-04-14 13:36:39
668	L´Hospitalet de Llobregat	ESP	Katalonia	247986	2007-11-15 16:26:18
669	Granada	ESP	Andalusia	244767	2004-01-02 22:01:51
670	A Coruña (La Coruña)	ESP	Galicia	243402	2012-03-27 18:13:28
671	Vitoria-Gasteiz	ESP	Baskimaa	217154	2010-10-19 19:16:09
672	Santa Cruz de Tenerife	ESP	Canary Islands	213050	2002-09-04 16:17:21
673	Badalona	ESP	Katalonia	209635	2009-08-26 03:21:28
674	Oviedo	ESP	Asturia	200453	2013-10-05 16:45:28
675	Móstoles	ESP	Madrid	195351	2013-09-17 17:03:09
676	Elche [Elx]	ESP	Valencia	193174	2003-08-20 01:54:17
677	Sabadell	ESP	Katalonia	184859	2009-03-28 23:20:18
678	Santander	ESP	Cantabria	184165	2011-01-08 05:03:31
679	Jerez de la Frontera	ESP	Andalusia	182660	2004-12-21 23:21:45
680	Pamplona [Iruña]	ESP	Navarra	180483	2002-01-01 04:37:43
681	Donostia-San Sebastián	ESP	Baskimaa	179208	2009-09-06 14:29:48
682	Cartagena	ESP	Murcia	177709	2004-02-10 02:14:01
683	Leganés	ESP	Madrid	173163	2013-12-08 05:48:03
684	Fuenlabrada	ESP	Madrid	171173	2004-07-12 11:26:20
685	Almería	ESP	Andalusia	169027	2011-03-10 11:44:59
686	Terrassa	ESP	Katalonia	168695	2005-01-24 23:33:41
687	Alcalá de Henares	ESP	Madrid	164463	2001-08-27 10:33:47
688	Burgos	ESP	Castilla and León	162802	2010-07-20 15:42:42
689	Salamanca	ESP	Castilla and León	158720	2007-12-07 11:04:27
690	Albacete	ESP	Kastilia-La Mancha	147527	2002-12-19 23:04:06
691	Getafe	ESP	Madrid	145371	2004-01-12 08:40:46
692	Cádiz	ESP	Andalusia	142449	2013-04-28 14:13:01
693	Alcorcón	ESP	Madrid	142048	2007-06-28 12:54:41
694	Huelva	ESP	Andalusia	140583	2001-10-18 20:59:29
695	León	ESP	Castilla and León	139809	2008-06-03 05:22:14
696	Castellón de la Plana [Castell	ESP	Valencia	139712	2007-04-27 23:50:06
697	Badajoz	ESP	Extremadura	136613	2010-04-28 21:41:39
698	[San Cristóbal de] la Laguna	ESP	Canary Islands	127945	2014-10-21 20:21:13
699	Logroño	ESP	La Rioja	127093	2014-04-22 12:55:17
700	Santa Coloma de Gramenet	ESP	Katalonia	120802	2011-08-23 05:26:40
701	Tarragona	ESP	Katalonia	113016	2012-05-13 14:42:13
702	Lleida (Lérida)	ESP	Katalonia	112207	2007-02-26 18:22:20
703	Jaén	ESP	Andalusia	109247	2009-02-12 23:34:12
704	Ourense (Orense)	ESP	Galicia	109120	2005-07-17 10:55:33
705	Mataró	ESP	Katalonia	104095	2013-02-18 02:08:05
706	Algeciras	ESP	Andalusia	103106	2005-06-25 10:54:49
707	Marbella	ESP	Andalusia	101144	2014-05-17 16:53:57
708	Barakaldo	ESP	Baskimaa	98212	2012-08-25 19:07:37
709	Dos Hermanas	ESP	Andalusia	94591	2003-07-14 23:02:58
710	Santiago de Compostela	ESP	Galicia	93745	2003-02-24 05:15:04
711	Torrejón de Ardoz	ESP	Madrid	92262	2014-10-24 23:49:04
712	Cape Town	ZAF	Western Cape	2352121	2010-09-15 19:52:58
713	Soweto	ZAF	Gauteng	904165	2006-06-23 13:53:58
714	Johannesburg	ZAF	Gauteng	756653	2010-07-17 23:13:12
715	Port Elizabeth	ZAF	Eastern Cape	752319	2002-01-08 05:18:45
716	Pretoria	ZAF	Gauteng	658630	2008-11-19 15:10:49
717	Inanda	ZAF	KwaZulu-Natal	634065	2006-01-19 15:32:59
718	Durban	ZAF	KwaZulu-Natal	566120	2005-07-12 22:26:07
719	Vanderbijlpark	ZAF	Gauteng	468931	2001-07-16 17:16:16
720	Kempton Park	ZAF	Gauteng	442633	2010-07-10 13:01:39
721	Alberton	ZAF	Gauteng	410102	2002-07-23 10:33:45
722	Pinetown	ZAF	KwaZulu-Natal	378810	2005-08-20 22:35:16
723	Pietermaritzburg	ZAF	KwaZulu-Natal	370190	2010-12-18 21:06:33
724	Benoni	ZAF	Gauteng	365467	2004-06-04 14:44:04
725	Randburg	ZAF	Gauteng	341288	2007-12-02 20:44:23
726	Umlazi	ZAF	KwaZulu-Natal	339233	2003-07-06 14:17:37
727	Bloemfontein	ZAF	Free State	334341	2012-12-28 08:43:14
728	Vereeniging	ZAF	Gauteng	328535	2005-08-22 22:21:47
729	Wonderboom	ZAF	Gauteng	283289	2014-11-22 02:51:07
730	Roodepoort	ZAF	Gauteng	279340	2012-03-25 02:08:37
731	Boksburg	ZAF	Gauteng	262648	2001-07-12 18:47:02
732	Klerksdorp	ZAF	North West	261911	2013-12-25 14:03:25
733	Soshanguve	ZAF	Gauteng	242727	2014-10-22 02:20:15
734	Newcastle	ZAF	KwaZulu-Natal	222993	2002-08-01 11:02:24
735	East London	ZAF	Eastern Cape	221047	2009-09-22 13:19:14
736	Welkom	ZAF	Free State	203296	2003-08-18 03:55:16
737	Kimberley	ZAF	Northern Cape	197254	2001-10-26 02:16:33
738	Uitenhage	ZAF	Eastern Cape	192120	2010-08-09 23:23:03
739	Chatsworth	ZAF	KwaZulu-Natal	189885	2010-12-25 16:28:39
740	Mdantsane	ZAF	Eastern Cape	182639	2008-07-22 19:51:27
741	Krugersdorp	ZAF	Gauteng	181503	2012-04-28 23:36:39
742	Botshabelo	ZAF	Free State	177971	2007-06-15 18:15:28
743	Brakpan	ZAF	Gauteng	171363	2014-07-09 05:58:50
744	Witbank	ZAF	Mpumalanga	167183	2012-11-19 16:58:43
745	Oberholzer	ZAF	Gauteng	164367	2010-10-15 03:10:25
746	Germiston	ZAF	Gauteng	164252	2011-12-10 11:40:58
747	Springs	ZAF	Gauteng	162072	2011-11-24 23:22:40
748	Westonaria	ZAF	Gauteng	159632	2005-04-14 17:47:36
749	Randfontein	ZAF	Gauteng	120838	2011-07-18 06:54:22
750	Paarl	ZAF	Western Cape	105768	2006-07-20 22:15:31
751	Potchefstroom	ZAF	North West	101817	2003-07-08 06:35:40
752	Rustenburg	ZAF	North West	97008	2004-07-16 18:56:41
753	Nigel	ZAF	Gauteng	96734	2011-10-16 14:29:31
754	George	ZAF	Western Cape	93818	2001-07-11 10:52:07
755	Ladysmith	ZAF	KwaZulu-Natal	89292	2013-09-26 18:35:53
756	Addis Abeba	ETH	Addis Abeba	2495000	2005-01-01 11:20:52
757	Dire Dawa	ETH	Dire Dawa	164851	2011-11-09 09:11:01
758	Nazret	ETH	Oromia	127842	2004-04-26 23:14:36
759	Gonder	ETH	Amhara	112249	2012-11-15 09:12:53
760	Dese	ETH	Amhara	97314	2012-12-27 01:40:30
761	Mekele	ETH	Tigray	96938	2005-06-08 09:32:02
762	Bahir Dar	ETH	Amhara	96140	2013-12-10 16:21:46
763	Stanley	FLK	East Falkland	1636	2013-05-07 07:50:44
764	Suva	FJI	Central	77366	2012-08-03 16:26:33
765	Quezon	PHL	National Capital Reg	2173831	2006-09-03 08:51:54
766	Manila	PHL	National Capital Reg	1581082	2009-04-23 14:31:41
767	Kalookan	PHL	National Capital Reg	1177604	2008-03-15 02:33:11
768	Davao	PHL	Southern Mindanao	1147116	2011-09-27 14:59:40
769	Cebu	PHL	Central Visayas	718821	2010-08-17 12:57:18
770	Zamboanga	PHL	Western Mindanao	601794	2006-06-27 23:07:20
771	Pasig	PHL	National Capital Reg	505058	2009-05-21 15:36:26
772	Valenzuela	PHL	National Capital Reg	485433	2012-08-09 08:33:12
773	Las Piñas	PHL	National Capital Reg	472780	2011-09-03 22:14:46
774	Antipolo	PHL	Southern Tagalog	470866	2005-02-04 10:25:55
775	Taguig	PHL	National Capital Reg	467375	2012-12-08 13:52:52
776	Cagayan de Oro	PHL	Northern Mindanao	461877	2012-08-08 05:11:16
777	Parañaque	PHL	National Capital Reg	449811	2010-07-19 04:41:31
778	Makati	PHL	National Capital Reg	444867	2009-09-28 22:48:58
779	Bacolod	PHL	Western Visayas	429076	2007-06-19 13:44:26
780	General Santos	PHL	Southern Mindanao	411822	2004-03-27 16:23:54
781	Marikina	PHL	National Capital Reg	391170	2001-11-16 05:55:44
782	Dasmariñas	PHL	Southern Tagalog	379520	2012-03-24 05:17:27
783	Muntinlupa	PHL	National Capital Reg	379310	2003-05-19 10:48:40
784	Iloilo	PHL	Western Visayas	365820	2010-01-12 05:37:24
785	Pasay	PHL	National Capital Reg	354908	2012-03-17 09:09:33
786	Malabon	PHL	National Capital Reg	338855	2006-04-25 23:03:21
787	San José del Monte	PHL	Central Luzon	315807	2003-06-08 21:32:11
788	Bacoor	PHL	Southern Tagalog	305699	2006-01-28 12:56:29
789	Iligan	PHL	Central Mindanao	285061	2011-12-12 06:29:09
790	Calamba	PHL	Southern Tagalog	281146	2012-02-28 07:49:17
791	Mandaluyong	PHL	National Capital Reg	278474	2008-03-02 05:05:37
792	Butuan	PHL	Caraga	267279	2005-03-27 05:18:30
793	Angeles	PHL	Central Luzon	263971	2009-08-10 11:01:18
794	Tarlac	PHL	Central Luzon	262481	2010-09-21 21:45:55
795	Mandaue	PHL	Central Visayas	259728	2008-04-13 16:49:33
796	Baguio	PHL	CAR	252386	2009-10-16 02:33:20
797	Batangas	PHL	Southern Tagalog	247588	2009-08-15 11:55:11
798	Cainta	PHL	Southern Tagalog	242511	2012-05-12 09:55:42
799	San Pedro	PHL	Southern Tagalog	231403	2005-02-17 06:57:14
800	Navotas	PHL	National Capital Reg	230403	2001-04-25 13:59:32
801	Cabanatuan	PHL	Central Luzon	222859	2010-01-26 05:46:57
802	San Fernando	PHL	Central Luzon	221857	2013-10-17 21:49:09
803	Lipa	PHL	Southern Tagalog	218447	2004-08-01 17:21:35
804	Lapu-Lapu	PHL	Central Visayas	217019	2003-03-18 19:15:31
805	San Pablo	PHL	Southern Tagalog	207927	2011-02-06 08:10:14
806	Biñan	PHL	Southern Tagalog	201186	2011-06-18 21:23:05
807	Taytay	PHL	Southern Tagalog	198183	2008-05-20 18:46:30
808	Lucena	PHL	Southern Tagalog	196075	2014-08-11 14:59:07
809	Imus	PHL	Southern Tagalog	195482	2008-10-13 07:59:50
810	Olongapo	PHL	Central Luzon	194260	2002-12-26 02:27:06
811	Binangonan	PHL	Southern Tagalog	187691	2009-11-10 14:36:45
812	Santa Rosa	PHL	Southern Tagalog	185633	2007-11-22 10:34:02
813	Tagum	PHL	Southern Mindanao	179531	2004-01-23 21:23:06
814	Tacloban	PHL	Eastern Visayas	178639	2008-10-09 02:05:29
815	Malolos	PHL	Central Luzon	175291	2002-04-19 06:22:16
816	Mabalacat	PHL	Central Luzon	171045	2011-10-01 20:11:03
817	Cotabato	PHL	Central Mindanao	163849	2014-06-10 16:55:34
818	Meycauayan	PHL	Central Luzon	163037	2002-09-21 08:28:23
819	Puerto Princesa	PHL	Southern Tagalog	161912	2013-04-20 10:15:03
820	Legazpi	PHL	Bicol	157010	2001-05-25 15:38:15
821	Silang	PHL	Southern Tagalog	156137	2014-02-26 23:07:07
822	Ormoc	PHL	Eastern Visayas	154297	2007-06-11 07:16:30
823	San Carlos	PHL	Ilocos	154264	2008-03-16 14:13:12
824	Kabankalan	PHL	Western Visayas	149769	2002-04-17 01:34:32
825	Talisay	PHL	Central Visayas	148110	2009-03-17 17:29:16
826	Valencia	PHL	Northern Mindanao	147924	2004-09-06 01:29:39
827	Calbayog	PHL	Eastern Visayas	147187	2004-06-16 10:16:47
828	Santa Maria	PHL	Central Luzon	144282	2004-02-26 02:16:10
829	Pagadian	PHL	Western Mindanao	142515	2004-03-26 22:32:20
830	Cadiz	PHL	Western Visayas	141954	2013-02-04 12:12:48
831	Bago	PHL	Western Visayas	141721	2005-06-14 10:12:35
832	Toledo	PHL	Central Visayas	141174	2006-01-01 22:29:08
833	Naga	PHL	Bicol	137810	2007-05-18 15:13:33
834	San Mateo	PHL	Southern Tagalog	135603	2011-08-18 20:40:18
835	Panabo	PHL	Southern Mindanao	133950	2001-07-19 03:28:55
836	Koronadal	PHL	Southern Mindanao	133786	2003-12-15 15:21:34
837	Marawi	PHL	Central Mindanao	131090	2012-04-19 11:25:19
838	Dagupan	PHL	Ilocos	130328	2012-09-02 05:47:58
839	Sagay	PHL	Western Visayas	129765	2006-09-23 03:06:58
840	Roxas	PHL	Western Visayas	126352	2003-03-02 10:37:22
841	Lubao	PHL	Central Luzon	125699	2006-09-26 11:03:08
842	Digos	PHL	Southern Mindanao	125171	2005-11-04 04:36:29
843	San Miguel	PHL	Central Luzon	123824	2012-04-02 11:57:20
844	Malaybalay	PHL	Northern Mindanao	123672	2013-04-22 08:38:56
845	Tuguegarao	PHL	Cagayan Valley	120645	2007-07-18 01:49:29
846	Ilagan	PHL	Cagayan Valley	119990	2005-07-27 03:15:16
847	Baliuag	PHL	Central Luzon	119675	2014-10-02 16:53:39
848	Surigao	PHL	Caraga	118534	2013-09-21 03:40:52
849	San Carlos	PHL	Western Visayas	118259	2011-10-14 03:44:43
850	San Juan del Monte	PHL	National Capital Reg	117680	2005-03-02 12:45:28
851	Tanauan	PHL	Southern Tagalog	117539	2005-02-16 18:07:53
852	Concepcion	PHL	Central Luzon	115171	2009-01-01 07:20:40
853	Rodriguez (Montalban)	PHL	Southern Tagalog	115167	2014-06-16 05:04:06
854	Sariaya	PHL	Southern Tagalog	114568	2003-11-17 21:41:09
855	Malasiqui	PHL	Ilocos	113190	2010-08-04 11:34:27
856	General Mariano Alvarez	PHL	Southern Tagalog	112446	2010-01-24 15:59:14
857	Urdaneta	PHL	Ilocos	111582	2007-03-12 11:31:54
858	Hagonoy	PHL	Central Luzon	111425	2001-03-25 03:14:12
859	San Jose	PHL	Southern Tagalog	111009	2011-12-02 03:35:25
860	Polomolok	PHL	Southern Mindanao	110709	2006-05-08 23:09:23
861	Santiago	PHL	Cagayan Valley	110531	2006-09-25 08:01:21
862	Tanza	PHL	Southern Tagalog	110517	2005-06-10 14:56:09
863	Ozamis	PHL	Northern Mindanao	110420	2005-02-24 04:05:26
864	Mexico	PHL	Central Luzon	109481	2014-05-19 08:19:11
865	San Jose	PHL	Central Luzon	108254	2003-07-23 19:33:31
866	Silay	PHL	Western Visayas	107722	2011-02-19 21:21:36
867	General Trias	PHL	Southern Tagalog	107691	2005-12-15 08:12:07
868	Tabaco	PHL	Bicol	107166	2009-05-05 15:03:04
869	Cabuyao	PHL	Southern Tagalog	106630	2002-08-25 05:02:07
870	Calapan	PHL	Southern Tagalog	105910	2013-01-11 14:34:54
871	Mati	PHL	Southern Mindanao	105908	2009-10-27 22:40:54
872	Midsayap	PHL	Central Mindanao	105760	2009-02-26 21:48:47
873	Cauayan	PHL	Cagayan Valley	103952	2005-09-01 07:22:39
874	Gingoog	PHL	Northern Mindanao	102379	2011-07-24 07:35:05
875	Dumaguete	PHL	Central Visayas	102265	2010-04-21 05:40:30
876	San Fernando	PHL	Ilocos	102082	2012-08-01 23:02:34
877	Arayat	PHL	Central Luzon	101792	2009-08-16 17:19:10
878	Bayawan (Tulong)	PHL	Central Visayas	101391	2005-07-26 16:19:31
879	Kidapawan	PHL	Central Mindanao	101205	2002-09-18 23:09:43
880	Daraga (Locsin)	PHL	Bicol	101031	2014-03-23 11:04:44
881	Marilao	PHL	Central Luzon	101017	2014-08-21 02:32:36
882	Malita	PHL	Southern Mindanao	100000	2014-02-14 02:28:57
883	Dipolog	PHL	Western Mindanao	99862	2004-10-10 23:50:11
884	Cavite	PHL	Southern Tagalog	99367	2014-05-17 13:06:38
885	Danao	PHL	Central Visayas	98781	2012-06-01 20:17:15
886	Bislig	PHL	Caraga	97860	2011-08-03 02:43:59
887	Talavera	PHL	Central Luzon	97329	2001-05-05 09:13:48
888	Guagua	PHL	Central Luzon	96858	2001-07-07 21:50:58
889	Bayambang	PHL	Ilocos	96609	2007-11-15 01:48:48
890	Nasugbu	PHL	Southern Tagalog	96113	2012-07-24 14:10:33
891	Baybay	PHL	Eastern Visayas	95630	2008-10-10 19:16:22
892	Capas	PHL	Central Luzon	95219	2002-12-28 04:11:15
893	Sultan Kudarat	PHL	ARMM	94861	2013-05-18 09:36:43
894	Laoag	PHL	Ilocos	94466	2001-03-11 08:03:53
895	Bayugan	PHL	Caraga	93623	2006-05-23 05:49:44
896	Malungon	PHL	Southern Mindanao	93232	2003-12-10 23:51:05
897	Santa Cruz	PHL	Southern Tagalog	92694	2008-08-23 06:11:37
898	Sorsogon	PHL	Bicol	92512	2002-01-09 14:28:35
899	Candelaria	PHL	Southern Tagalog	92429	2007-05-11 18:23:43
900	Ligao	PHL	Bicol	90603	2009-12-24 19:50:33
901	Tórshavn	FRO	Streymoyar	14542	2014-07-01 14:33:19
902	Libreville	GAB	Estuaire	419000	2013-04-25 12:15:57
903	Serekunda	GMB	Kombo St Mary	102600	2011-01-23 07:20:23
904	Banjul	GMB	Banjul	42326	2007-10-23 23:27:54
905	Tbilisi	GEO	Tbilisi	1235200	2012-06-24 19:09:08
906	Kutaisi	GEO	Imereti	240900	2011-02-10 19:52:44
907	Rustavi	GEO	Kvemo Kartli	155400	2003-04-10 11:04:01
908	Batumi	GEO	Adzaria [Atara]	137700	2005-07-27 02:46:44
909	Sohumi	GEO	Abhasia [Aphazeti]	111700	2014-07-03 23:50:23
910	Accra	GHA	Greater Accra	1070000	2005-12-24 02:04:53
911	Kumasi	GHA	Ashanti	385192	2013-09-01 23:33:09
912	Tamale	GHA	Northern	151069	2011-02-25 09:51:11
913	Tema	GHA	Greater Accra	109975	2011-11-18 16:55:17
914	Sekondi-Takoradi	GHA	Western	103653	2005-08-06 15:09:16
915	Gibraltar	GIB		27025	2002-08-06 08:17:34
916	Saint George´s	GRD	St George	4621	2013-04-06 12:59:06
917	Nuuk	GRL	Kitaa	13445	2006-01-13 16:08:55
918	Les Abymes	GLP	Grande-Terre	62947	2011-05-13 13:02:23
919	Basse-Terre	GLP	Basse-Terre	12433	2007-12-20 16:36:14
920	Tamuning	GUM		9500	2006-12-14 23:19:50
921	Agaña	GUM		1139	2008-08-26 04:58:19
922	Ciudad de Guatemala	GTM	Guatemala	823301	2012-06-18 23:54:57
923	Mixco	GTM	Guatemala	209791	2009-01-21 09:45:13
924	Villa Nueva	GTM	Guatemala	101295	2014-03-08 05:54:54
925	Quetzaltenango	GTM	Quetzaltenango	90801	2014-10-27 22:32:44
926	Conakry	GIN	Conakry	1090610	2011-01-10 03:14:19
927	Bissau	GNB	Bissau	241000	2009-12-02 14:26:27
928	Georgetown	GUY	Georgetown	254000	2014-09-06 03:28:07
929	Port-au-Prince	HTI	Ouest	884472	2008-07-18 19:05:44
930	Carrefour	HTI	Ouest	290204	2003-09-24 09:45:10
931	Delmas	HTI	Ouest	240429	2004-04-12 21:52:10
932	Le-Cap-Haïtien	HTI	Nord	102233	2012-02-07 14:45:19
933	Tegucigalpa	HND	Distrito Central	813900	2004-08-09 23:23:41
934	San Pedro Sula	HND	Cortés	383900	2007-05-22 11:58:38
935	La Ceiba	HND	Atlántida	89200	2011-02-01 20:22:53
936	Kowloon and New Kowloon	HKG	Kowloon and New Kowl	1987996	2010-05-26 20:48:42
937	Victoria	HKG	Hongkong	1312637	2001-09-08 04:23:42
938	Longyearbyen	SJM	Länsimaa	1438	2004-06-13 10:44:16
939	Jakarta	IDN	Jakarta Raya	9604900	2002-03-07 22:02:43
940	Surabaya	IDN	East Java	2663820	2006-10-10 15:36:02
941	Bandung	IDN	West Java	2429000	2006-11-07 10:52:47
942	Medan	IDN	Sumatera Utara	1843919	2008-07-23 11:58:11
943	Palembang	IDN	Sumatera Selatan	1222764	2010-07-22 12:45:45
944	Tangerang	IDN	West Java	1198300	2004-06-28 09:12:07
945	Semarang	IDN	Central Java	1104405	2013-05-02 19:40:21
946	Ujung Pandang	IDN	Sulawesi Selatan	1060257	2010-08-05 14:17:53
947	Malang	IDN	East Java	716862	2010-09-04 18:34:16
948	Bandar Lampung	IDN	Lampung	680332	2013-04-03 18:35:43
949	Bekasi	IDN	West Java	644300	2002-05-21 10:05:17
950	Padang	IDN	Sumatera Barat	534474	2005-10-09 19:39:32
951	Surakarta	IDN	Central Java	518600	2001-09-17 05:50:26
952	Banjarmasin	IDN	Kalimantan Selatan	482931	2013-05-23 04:49:16
953	Pekan Baru	IDN	Riau	438638	2011-05-19 23:29:37
954	Denpasar	IDN	Bali	435000	2004-06-04 05:11:02
955	Yogyakarta	IDN	Yogyakarta	418944	2006-04-02 13:39:24
956	Pontianak	IDN	Kalimantan Barat	409632	2001-08-04 16:06:47
957	Samarinda	IDN	Kalimantan Timur	399175	2011-06-15 12:54:13
958	Jambi	IDN	Jambi	385201	2003-08-24 18:33:12
959	Depok	IDN	West Java	365200	2003-02-21 16:22:12
960	Cimahi	IDN	West Java	344600	2010-11-02 07:54:36
961	Balikpapan	IDN	Kalimantan Timur	338752	2004-04-12 07:12:44
962	Manado	IDN	Sulawesi Utara	332288	2011-07-26 17:26:27
963	Mataram	IDN	Nusa Tenggara Barat	306600	2013-05-11 10:05:14
964	Pekalongan	IDN	Central Java	301504	2004-11-07 19:55:12
965	Tegal	IDN	Central Java	289744	2006-01-09 23:14:02
966	Bogor	IDN	West Java	285114	2011-12-27 03:12:09
967	Ciputat	IDN	West Java	270800	2001-03-08 17:48:51
968	Pondokgede	IDN	West Java	263200	2009-06-11 13:52:09
969	Cirebon	IDN	West Java	254406	2009-02-27 02:09:39
970	Kediri	IDN	East Java	253760	2009-01-05 18:18:34
971	Ambon	IDN	Molukit	249312	2010-04-05 20:36:48
972	Jember	IDN	East Java	218500	2013-10-27 09:41:11
973	Cilacap	IDN	Central Java	206900	2014-12-24 23:23:10
974	Cimanggis	IDN	West Java	205100	2007-11-13 22:37:21
975	Pematang Siantar	IDN	Sumatera Utara	203056	2008-06-18 15:22:12
976	Purwokerto	IDN	Central Java	202500	2002-10-14 08:50:54
977	Ciomas	IDN	West Java	187400	2013-12-02 10:59:25
978	Tasikmalaya	IDN	West Java	179800	2003-01-26 15:56:31
979	Madiun	IDN	East Java	171532	2007-07-10 12:23:48
980	Bengkulu	IDN	Bengkulu	146439	2012-03-02 15:40:30
981	Karawang	IDN	West Java	145000	2003-11-13 02:45:51
982	Banda Aceh	IDN	Aceh	143409	2006-04-04 14:02:09
983	Palu	IDN	Sulawesi Tengah	142800	2012-08-06 15:44:22
984	Pasuruan	IDN	East Java	134019	2005-10-07 07:19:02
985	Kupang	IDN	Nusa Tenggara Timur	129300	2011-03-16 14:46:56
986	Tebing Tinggi	IDN	Sumatera Utara	129300	2013-08-16 21:34:40
987	Percut Sei Tuan	IDN	Sumatera Utara	129000	2002-12-10 17:11:12
988	Binjai	IDN	Sumatera Utara	127222	2009-03-15 15:51:38
989	Sukabumi	IDN	West Java	125766	2001-12-16 22:12:04
990	Waru	IDN	East Java	124300	2012-11-24 19:20:51
991	Pangkal Pinang	IDN	Sumatera Selatan	124000	2013-08-06 02:27:58
992	Magelang	IDN	Central Java	123800	2001-05-09 09:07:30
993	Blitar	IDN	East Java	122600	2007-02-22 15:10:28
994	Serang	IDN	West Java	122400	2004-05-01 11:27:20
995	Probolinggo	IDN	East Java	120770	2007-11-23 12:29:16
996	Cilegon	IDN	West Java	117000	2013-11-19 22:07:33
997	Cianjur	IDN	West Java	114300	2006-01-02 10:31:37
998	Ciparay	IDN	West Java	111500	2007-08-02 14:47:03
999	Lhokseumawe	IDN	Aceh	109600	2006-08-20 13:16:37
1000	Taman	IDN	East Java	107000	2002-07-03 23:17:44
1001	Depok	IDN	Yogyakarta	106800	2006-02-18 19:27:50
1002	Citeureup	IDN	West Java	105100	2003-05-14 10:59:20
1003	Pemalang	IDN	Central Java	103500	2006-08-16 17:26:58
1004	Klaten	IDN	Central Java	103300	2006-05-01 10:39:12
1005	Salatiga	IDN	Central Java	103000	2007-12-04 21:15:28
1006	Cibinong	IDN	West Java	101300	2013-07-06 17:46:10
1007	Palangka Raya	IDN	Kalimantan Tengah	99693	2004-11-23 16:36:04
1008	Mojokerto	IDN	East Java	96626	2014-02-16 18:19:37
1009	Purwakarta	IDN	West Java	95900	2006-08-02 03:57:46
1010	Garut	IDN	West Java	95800	2010-01-22 11:23:57
1011	Kudus	IDN	Central Java	95300	2010-01-03 19:32:35
1012	Kendari	IDN	Sulawesi Tenggara	94800	2009-01-12 14:35:42
1013	Jaya Pura	IDN	West Irian	94700	2006-11-20 18:53:30
1014	Gorontalo	IDN	Sulawesi Utara	94058	2004-07-25 19:32:30
1015	Majalaya	IDN	West Java	93200	2008-03-17 13:49:34
1016	Pondok Aren	IDN	West Java	92700	2007-09-03 19:03:16
1017	Jombang	IDN	East Java	92600	2005-06-12 04:21:33
1018	Sunggal	IDN	Sumatera Utara	92300	2008-09-03 20:03:58
1019	Batam	IDN	Riau	91871	2011-02-11 11:06:15
1020	Padang Sidempuan	IDN	Sumatera Utara	91200	2001-10-21 19:13:04
1021	Sawangan	IDN	West Java	91100	2012-10-27 17:38:23
1022	Banyuwangi	IDN	East Java	89900	2004-10-08 10:29:52
1023	Tanjung Pinang	IDN	Riau	89900	2013-06-06 12:45:34
1024	Mumbai (Bombay)	IND	Maharashtra	10500000	2004-03-09 05:43:47
1025	Delhi	IND	Delhi	7206704	2011-11-20 21:53:47
1026	Calcutta [Kolkata]	IND	West Bengali	4399819	2004-02-05 15:56:01
1027	Chennai (Madras)	IND	Tamil Nadu	3841396	2008-03-25 16:23:55
1028	Hyderabad	IND	Andhra Pradesh	2964638	2008-10-20 01:08:50
1029	Ahmedabad	IND	Gujarat	2876710	2006-10-06 04:33:07
1030	Bangalore	IND	Karnataka	2660088	2006-11-21 21:27:55
1031	Kanpur	IND	Uttar Pradesh	1874409	2011-07-28 23:29:33
1032	Nagpur	IND	Maharashtra	1624752	2009-04-23 20:27:38
1033	Lucknow	IND	Uttar Pradesh	1619115	2007-12-04 23:13:06
1034	Pune	IND	Maharashtra	1566651	2010-01-19 12:02:10
1035	Surat	IND	Gujarat	1498817	2003-12-26 03:04:39
1036	Jaipur	IND	Rajasthan	1458483	2014-04-08 09:20:05
1037	Indore	IND	Madhya Pradesh	1091674	2012-01-23 16:33:11
1038	Bhopal	IND	Madhya Pradesh	1062771	2004-12-15 07:32:12
1039	Ludhiana	IND	Punjab	1042740	2007-05-07 10:18:13
1040	Vadodara (Baroda)	IND	Gujarat	1031346	2013-06-12 10:33:30
1041	Kalyan	IND	Maharashtra	1014557	2006-06-26 04:42:37
1042	Madurai	IND	Tamil Nadu	977856	2009-12-16 01:37:11
1043	Haora (Howrah)	IND	West Bengali	950435	2002-06-15 14:02:46
1044	Varanasi (Benares)	IND	Uttar Pradesh	929270	2012-02-23 14:31:23
1045	Patna	IND	Bihar	917243	2014-07-02 06:04:27
1046	Srinagar	IND	Jammu and Kashmir	892506	2002-04-07 08:36:06
1047	Agra	IND	Uttar Pradesh	891790	2010-10-08 17:48:15
1048	Coimbatore	IND	Tamil Nadu	816321	2012-03-26 20:25:27
1049	Thane (Thana)	IND	Maharashtra	803389	2005-12-22 09:43:06
1050	Allahabad	IND	Uttar Pradesh	792858	2004-06-26 07:18:07
1051	Meerut	IND	Uttar Pradesh	753778	2013-06-15 07:42:55
1052	Vishakhapatnam	IND	Andhra Pradesh	752037	2014-11-20 11:49:44
1053	Jabalpur	IND	Madhya Pradesh	741927	2005-04-23 18:26:38
1054	Amritsar	IND	Punjab	708835	2005-10-16 05:23:48
1055	Faridabad	IND	Haryana	703592	2008-07-17 11:34:15
1056	Vijayawada	IND	Andhra Pradesh	701827	2002-07-14 02:34:07
1057	Gwalior	IND	Madhya Pradesh	690765	2003-07-07 11:47:17
1058	Jodhpur	IND	Rajasthan	666279	2001-01-14 16:06:25
1059	Nashik (Nasik)	IND	Maharashtra	656925	2012-08-13 22:23:03
1060	Hubli-Dharwad	IND	Karnataka	648298	2001-09-14 05:57:43
1061	Solapur (Sholapur)	IND	Maharashtra	604215	2010-12-13 10:20:15
1062	Ranchi	IND	Jharkhand	599306	2011-10-19 03:40:36
1063	Bareilly	IND	Uttar Pradesh	587211	2011-11-14 15:44:23
1064	Guwahati (Gauhati)	IND	Assam	584342	2011-06-23 04:05:11
1065	Shambajinagar (Aurangabad)	IND	Maharashtra	573272	2001-12-24 09:05:43
1066	Cochin (Kochi)	IND	Kerala	564589	2003-04-25 19:26:47
1067	Rajkot	IND	Gujarat	559407	2009-12-15 09:55:39
1068	Kota	IND	Rajasthan	537371	2007-06-15 17:47:35
1069	Thiruvananthapuram (Trivandrum	IND	Kerala	524006	2002-07-27 04:23:02
1070	Pimpri-Chinchwad	IND	Maharashtra	517083	2014-07-15 01:10:32
1071	Jalandhar (Jullundur)	IND	Punjab	509510	2011-07-20 09:21:08
1072	Gorakhpur	IND	Uttar Pradesh	505566	2004-12-03 14:58:56
1073	Chandigarh	IND	Chandigarh	504094	2013-12-03 22:55:23
1074	Mysore	IND	Karnataka	480692	2010-09-26 03:08:56
1075	Aligarh	IND	Uttar Pradesh	480520	2004-11-28 17:43:26
1076	Guntur	IND	Andhra Pradesh	471051	2013-08-09 10:10:08
1077	Jamshedpur	IND	Jharkhand	460577	2007-10-16 23:46:03
1078	Ghaziabad	IND	Uttar Pradesh	454156	2003-05-11 05:36:03
1079	Warangal	IND	Andhra Pradesh	447657	2002-04-12 23:51:11
1080	Raipur	IND	Chhatisgarh	438639	2001-09-05 11:16:56
1081	Moradabad	IND	Uttar Pradesh	429214	2008-11-12 08:58:05
1082	Durgapur	IND	West Bengali	425836	2003-01-04 17:58:10
1083	Amravati	IND	Maharashtra	421576	2013-01-12 01:26:34
1084	Calicut (Kozhikode)	IND	Kerala	419831	2002-08-17 15:28:56
1085	Bikaner	IND	Rajasthan	416289	2010-10-16 16:48:33
1086	Bhubaneswar	IND	Orissa	411542	2014-10-08 18:05:48
1087	Kolhapur	IND	Maharashtra	406370	2001-02-14 05:03:28
1088	Kataka (Cuttack)	IND	Orissa	403418	2010-01-11 17:15:08
1089	Ajmer	IND	Rajasthan	402700	2006-08-04 23:29:35
1090	Bhavnagar	IND	Gujarat	402338	2009-10-06 14:54:13
1091	Tiruchirapalli	IND	Tamil Nadu	387223	2001-01-04 06:28:41
1092	Bhilai	IND	Chhatisgarh	386159	2002-01-09 06:46:44
1093	Bhiwandi	IND	Maharashtra	379070	2013-05-23 07:44:38
1094	Saharanpur	IND	Uttar Pradesh	374945	2014-01-09 09:22:41
1095	Ulhasnagar	IND	Maharashtra	369077	2004-08-01 22:31:13
1096	Salem	IND	Tamil Nadu	366712	2010-01-01 07:33:23
1097	Ujjain	IND	Madhya Pradesh	362266	2012-09-12 04:16:46
1098	Malegaon	IND	Maharashtra	342595	2008-01-24 23:28:42
1099	Jamnagar	IND	Gujarat	341637	2002-10-10 15:57:26
1100	Bokaro Steel City	IND	Jharkhand	333683	2004-04-09 08:29:23
1101	Akola	IND	Maharashtra	328034	2006-04-11 09:28:10
1102	Belgaum	IND	Karnataka	326399	2011-01-26 15:18:33
1103	Rajahmundry	IND	Andhra Pradesh	324851	2007-03-16 05:30:37
1104	Nellore	IND	Andhra Pradesh	316606	2006-05-27 10:41:15
1105	Udaipur	IND	Rajasthan	308571	2014-02-13 03:50:36
1106	New Bombay	IND	Maharashtra	307297	2009-03-24 22:58:40
1107	Bhatpara	IND	West Bengali	304952	2008-01-13 11:04:46
1108	Gulbarga	IND	Karnataka	304099	2002-04-20 01:17:10
1109	New Delhi	IND	Delhi	301297	2004-01-15 22:17:42
1110	Jhansi	IND	Uttar Pradesh	300850	2005-06-17 22:12:41
1111	Gaya	IND	Bihar	291675	2009-02-02 16:40:36
1112	Kakinada	IND	Andhra Pradesh	279980	2012-05-28 22:42:21
1113	Dhule (Dhulia)	IND	Maharashtra	278317	2001-06-14 09:11:47
1114	Panihati	IND	West Bengali	275990	2012-04-09 23:41:04
1115	Nanded (Nander)	IND	Maharashtra	275083	2001-04-01 12:18:30
1116	Mangalore	IND	Karnataka	273304	2006-01-08 14:20:37
1117	Dehra Dun	IND	Uttaranchal	270159	2009-11-11 14:07:55
1118	Kamarhati	IND	West Bengali	266889	2005-08-20 16:27:10
1119	Davangere	IND	Karnataka	266082	2006-08-18 16:07:39
1120	Asansol	IND	West Bengali	262188	2007-02-03 16:42:12
1121	Bhagalpur	IND	Bihar	253225	2010-05-19 09:49:13
1122	Bellary	IND	Karnataka	245391	2007-04-16 23:21:46
1123	Barddhaman (Burdwan)	IND	West Bengali	245079	2014-05-09 09:17:06
1124	Rampur	IND	Uttar Pradesh	243742	2007-11-10 03:04:54
1125	Jalgaon	IND	Maharashtra	242193	2004-05-25 13:56:24
1126	Muzaffarpur	IND	Bihar	241107	2012-04-14 01:09:15
1127	Nizamabad	IND	Andhra Pradesh	241034	2010-07-12 14:53:45
1128	Muzaffarnagar	IND	Uttar Pradesh	240609	2008-05-27 16:28:33
1129	Patiala	IND	Punjab	238368	2013-08-06 06:12:11
1130	Shahjahanpur	IND	Uttar Pradesh	237713	2007-05-04 06:27:01
1131	Kurnool	IND	Andhra Pradesh	236800	2008-06-12 21:10:36
1132	Tiruppur (Tirupper)	IND	Tamil Nadu	235661	2014-07-16 11:32:24
1133	Rohtak	IND	Haryana	233400	2006-06-02 10:05:44
1134	South Dum Dum	IND	West Bengali	232811	2008-10-22 07:09:45
1135	Mathura	IND	Uttar Pradesh	226691	2003-11-12 13:23:05
1136	Chandrapur	IND	Maharashtra	226105	2009-12-01 23:04:33
1137	Barahanagar (Baranagar)	IND	West Bengali	224821	2007-05-03 23:43:16
1138	Darbhanga	IND	Bihar	218391	2001-03-13 04:23:46
1139	Siliguri (Shiliguri)	IND	West Bengali	216950	2005-09-05 11:29:19
1140	Raurkela	IND	Orissa	215489	2010-10-22 15:10:53
1141	Ambattur	IND	Tamil Nadu	215424	2010-08-20 06:59:50
1142	Panipat	IND	Haryana	215218	2006-09-23 17:32:07
1143	Firozabad	IND	Uttar Pradesh	215128	2007-05-28 10:32:20
1144	Ichalkaranji	IND	Maharashtra	214950	2004-03-10 15:41:48
1145	Jammu	IND	Jammu and Kashmir	214737	2005-09-14 13:33:30
1146	Ramagundam	IND	Andhra Pradesh	214384	2001-03-01 04:08:49
1147	Eluru	IND	Andhra Pradesh	212866	2014-05-02 23:54:17
1148	Brahmapur	IND	Orissa	210418	2001-09-26 04:16:21
1149	Alwar	IND	Rajasthan	205086	2011-11-05 23:38:48
1150	Pondicherry	IND	Pondicherry	203065	2005-02-27 02:32:20
1151	Thanjavur	IND	Tamil Nadu	202013	2010-05-21 02:19:41
1152	Bihar Sharif	IND	Bihar	201323	2010-02-03 07:39:14
1153	Tuticorin	IND	Tamil Nadu	199854	2008-07-06 15:20:23
1154	Imphal	IND	Manipur	198535	2013-10-27 15:59:47
1155	Latur	IND	Maharashtra	197408	2005-05-27 04:56:33
1156	Sagar	IND	Madhya Pradesh	195346	2002-04-26 22:23:30
1157	Farrukhabad-cum-Fatehgarh	IND	Uttar Pradesh	194567	2009-08-14 14:58:30
1158	Sangli	IND	Maharashtra	193197	2006-02-19 20:32:31
1159	Parbhani	IND	Maharashtra	190255	2006-08-17 23:58:43
1160	Nagar Coil	IND	Tamil Nadu	190084	2010-10-22 01:05:20
1161	Bijapur	IND	Karnataka	186939	2007-01-08 21:44:21
1162	Kukatpalle	IND	Andhra Pradesh	185378	2006-02-10 12:31:08
1163	Bally	IND	West Bengali	184474	2005-08-12 17:11:59
1164	Bhilwara	IND	Rajasthan	183965	2013-06-28 13:39:44
1165	Ratlam	IND	Madhya Pradesh	183375	2003-09-15 07:07:57
1166	Avadi	IND	Tamil Nadu	183215	2007-07-10 02:18:23
1167	Dindigul	IND	Tamil Nadu	182477	2001-01-25 18:23:02
1168	Ahmadnagar	IND	Maharashtra	181339	2006-06-14 17:34:54
1169	Bilaspur	IND	Chhatisgarh	179833	2005-07-04 09:48:50
1170	Shimoga	IND	Karnataka	179258	2004-12-02 22:42:47
1171	Kharagpur	IND	West Bengali	177989	2013-11-01 22:01:15
1172	Mira Bhayandar	IND	Maharashtra	175372	2005-08-13 19:02:01
1173	Vellore	IND	Tamil Nadu	175061	2009-01-22 10:55:13
1174	Jalna	IND	Maharashtra	174985	2006-08-12 22:55:24
1175	Burnpur	IND	West Bengali	174933	2011-03-16 23:39:40
1176	Anantapur	IND	Andhra Pradesh	174924	2003-07-16 14:52:28
1177	Allappuzha (Alleppey)	IND	Kerala	174666	2002-04-06 01:16:44
1178	Tirupati	IND	Andhra Pradesh	174369	2007-08-03 14:12:22
1179	Karnal	IND	Haryana	173751	2006-04-04 10:50:33
1180	Burhanpur	IND	Madhya Pradesh	172710	2009-10-01 13:07:05
1181	Hisar (Hissar)	IND	Haryana	172677	2006-08-05 18:49:46
1182	Tiruvottiyur	IND	Tamil Nadu	172562	2012-08-13 22:58:16
1183	Mirzapur-cum-Vindhyachal	IND	Uttar Pradesh	169336	2007-10-07 05:37:12
1184	Secunderabad	IND	Andhra Pradesh	167461	2001-08-21 09:33:16
1185	Nadiad	IND	Gujarat	167051	2002-07-18 05:10:26
1186	Dewas	IND	Madhya Pradesh	164364	2007-08-19 09:54:13
1187	Murwara (Katni)	IND	Madhya Pradesh	163431	2003-03-07 01:18:32
1188	Ganganagar	IND	Rajasthan	161482	2004-02-21 11:46:22
1189	Vizianagaram	IND	Andhra Pradesh	160359	2007-07-06 12:02:40
1190	Erode	IND	Tamil Nadu	159232	2014-06-17 15:01:03
1191	Machilipatnam (Masulipatam)	IND	Andhra Pradesh	159110	2012-04-06 02:35:52
1192	Bhatinda (Bathinda)	IND	Punjab	159042	2013-10-16 05:29:33
1193	Raichur	IND	Karnataka	157551	2009-09-15 16:09:19
1194	Agartala	IND	Tripura	157358	2012-06-01 11:01:18
1195	Arrah (Ara)	IND	Bihar	157082	2004-07-24 22:28:19
1196	Satna	IND	Madhya Pradesh	156630	2001-07-21 08:27:09
1197	Lalbahadur Nagar	IND	Andhra Pradesh	155500	2003-11-18 08:30:37
1198	Aizawl	IND	Mizoram	155240	2009-08-16 23:10:33
1199	Uluberia	IND	West Bengali	155172	2005-02-10 15:17:07
1200	Katihar	IND	Bihar	154367	2004-01-10 07:43:30
1201	Cuddalore	IND	Tamil Nadu	153086	2012-01-26 22:21:52
1202	Hugli-Chinsurah	IND	West Bengali	151806	2009-11-18 18:39:19
1203	Dhanbad	IND	Jharkhand	151789	2002-08-12 23:44:42
1204	Raiganj	IND	West Bengali	151045	2011-11-10 02:13:22
1205	Sambhal	IND	Uttar Pradesh	150869	2003-01-24 20:12:30
1206	Durg	IND	Chhatisgarh	150645	2004-01-09 01:44:42
1207	Munger (Monghyr)	IND	Bihar	150112	2009-07-21 08:25:11
1208	Kanchipuram	IND	Tamil Nadu	150100	2004-10-12 10:18:53
1209	North Dum Dum	IND	West Bengali	149965	2012-10-19 19:10:45
1210	Karimnagar	IND	Andhra Pradesh	148583	2013-07-20 15:12:22
1211	Bharatpur	IND	Rajasthan	148519	2013-09-16 23:05:36
1212	Sikar	IND	Rajasthan	148272	2009-04-14 14:08:04
1213	Hardwar (Haridwar)	IND	Uttaranchal	147305	2002-11-19 14:12:15
1214	Dabgram	IND	West Bengali	147217	2008-05-15 21:18:24
1215	Morena	IND	Madhya Pradesh	147124	2003-02-10 16:10:49
1216	Noida	IND	Uttar Pradesh	146514	2011-06-20 03:29:47
1217	Hapur	IND	Uttar Pradesh	146262	2008-10-05 18:39:48
1218	Bhusawal	IND	Maharashtra	145143	2007-11-04 14:02:51
1219	Khandwa	IND	Madhya Pradesh	145133	2009-02-19 07:49:54
1220	Yamuna Nagar	IND	Haryana	144346	2009-01-01 05:22:49
1221	Sonipat (Sonepat)	IND	Haryana	143922	2012-07-14 19:27:33
1222	Tenali	IND	Andhra Pradesh	143726	2003-02-25 21:33:54
1223	Raurkela Civil Township	IND	Orissa	140408	2007-05-13 19:22:30
1224	Kollam (Quilon)	IND	Kerala	139852	2014-05-08 02:06:11
1225	Kumbakonam	IND	Tamil Nadu	139483	2013-12-01 17:41:43
1226	Ingraj Bazar (English Bazar)	IND	West Bengali	139204	2005-09-16 23:14:59
1227	Timkur	IND	Karnataka	138903	2004-08-01 23:33:45
1228	Amroha	IND	Uttar Pradesh	137061	2001-09-04 09:59:58
1229	Serampore	IND	West Bengali	137028	2001-10-12 06:43:27
1230	Chapra	IND	Bihar	136877	2004-07-16 23:08:04
1231	Pali	IND	Rajasthan	136842	2012-08-28 23:35:07
1232	Maunath Bhanjan	IND	Uttar Pradesh	136697	2006-05-14 22:01:17
1233	Adoni	IND	Andhra Pradesh	136182	2002-03-25 03:10:50
1234	Jaunpur	IND	Uttar Pradesh	136062	2006-03-16 09:01:46
1235	Tirunelveli	IND	Tamil Nadu	135825	2008-06-14 05:51:02
1236	Bahraich	IND	Uttar Pradesh	135400	2014-11-18 18:48:53
1237	Gadag Betigeri	IND	Karnataka	134051	2010-06-04 04:48:11
1238	Proddatur	IND	Andhra Pradesh	133914	2014-04-24 20:29:41
1239	Chittoor	IND	Andhra Pradesh	133462	2001-06-23 23:47:29
1240	Barrackpur	IND	West Bengali	133265	2006-12-25 02:17:51
1241	Bharuch (Broach)	IND	Gujarat	133102	2011-08-23 11:39:23
1242	Naihati	IND	West Bengali	132701	2012-04-20 11:48:20
1243	Shillong	IND	Meghalaya	131719	2001-08-24 11:08:51
1244	Sambalpur	IND	Orissa	131138	2005-06-15 19:37:21
1245	Junagadh	IND	Gujarat	130484	2005-11-04 18:48:17
1246	Rae Bareli	IND	Uttar Pradesh	129904	2011-01-07 03:25:13
1247	Rewa	IND	Madhya Pradesh	128981	2010-07-02 15:17:29
1248	Gurgaon	IND	Haryana	128608	2013-07-08 04:55:31
1249	Khammam	IND	Andhra Pradesh	127992	2011-06-13 09:55:47
1250	Bulandshahr	IND	Uttar Pradesh	127201	2004-12-02 05:29:10
1251	Navsari	IND	Gujarat	126089	2007-07-07 17:31:50
1252	Malkajgiri	IND	Andhra Pradesh	126066	2001-09-01 10:14:50
1253	Midnapore (Medinipur)	IND	West Bengali	125498	2004-05-07 22:46:44
1254	Miraj	IND	Maharashtra	125407	2011-11-03 09:46:28
1255	Raj Nandgaon	IND	Chhatisgarh	125371	2006-03-05 12:36:54
1256	Alandur	IND	Tamil Nadu	125244	2006-01-26 05:19:21
1257	Puri	IND	Orissa	125199	2006-12-12 23:42:57
1258	Navadwip	IND	West Bengali	125037	2011-11-02 18:31:55
1259	Sirsa	IND	Haryana	125000	2012-07-17 07:07:46
1260	Korba	IND	Chhatisgarh	124501	2011-06-23 02:46:31
1261	Faizabad	IND	Uttar Pradesh	124437	2012-08-10 11:59:55
1262	Etawah	IND	Uttar Pradesh	124072	2007-11-10 03:37:01
1263	Pathankot	IND	Punjab	123930	2003-01-06 23:27:13
1264	Gandhinagar	IND	Gujarat	123359	2002-05-09 02:59:59
1265	Palghat (Palakkad)	IND	Kerala	123289	2007-11-02 01:33:53
1266	Veraval	IND	Gujarat	123000	2012-01-17 17:48:27
1267	Hoshiarpur	IND	Punjab	122705	2012-01-22 19:25:35
1268	Ambala	IND	Haryana	122596	2007-04-20 01:11:59
1269	Sitapur	IND	Uttar Pradesh	121842	2007-09-18 04:48:54
1270	Bhiwani	IND	Haryana	121629	2002-04-09 11:48:32
1271	Cuddapah	IND	Andhra Pradesh	121463	2003-02-06 03:33:56
1272	Bhimavaram	IND	Andhra Pradesh	121314	2014-09-26 08:14:33
1273	Krishnanagar	IND	West Bengali	121110	2013-06-17 21:11:04
1274	Chandannagar	IND	West Bengali	120378	2010-07-26 04:54:40
1275	Mandya	IND	Karnataka	120265	2009-11-24 23:04:24
1276	Dibrugarh	IND	Assam	120127	2004-12-22 03:43:25
1277	Nandyal	IND	Andhra Pradesh	119813	2013-03-27 10:29:19
1278	Balurghat	IND	West Bengali	119796	2008-03-05 06:18:43
1279	Neyveli	IND	Tamil Nadu	118080	2004-01-23 17:08:50
1280	Fatehpur	IND	Uttar Pradesh	117675	2003-04-19 07:39:47
1281	Mahbubnagar	IND	Andhra Pradesh	116833	2010-10-25 16:04:51
1282	Budaun	IND	Uttar Pradesh	116695	2005-11-12 04:45:48
1283	Porbandar	IND	Gujarat	116671	2012-01-10 21:16:47
1284	Silchar	IND	Assam	115483	2005-01-17 11:03:34
1285	Berhampore (Baharampur)	IND	West Bengali	115144	2007-01-10 13:51:07
1286	Purnea (Purnia)	IND	Jharkhand	114912	2014-05-23 17:18:07
1287	Bankura	IND	West Bengali	114876	2003-02-20 02:46:28
1288	Rajapalaiyam	IND	Tamil Nadu	114202	2006-09-26 05:06:02
1289	Titagarh	IND	West Bengali	114085	2001-02-28 16:39:09
1290	Halisahar	IND	West Bengali	114028	2007-03-17 03:51:08
1291	Hathras	IND	Uttar Pradesh	113285	2003-06-24 12:54:09
1292	Bhir (Bid)	IND	Maharashtra	112434	2012-11-19 04:57:13
1293	Pallavaram	IND	Tamil Nadu	111866	2012-05-27 13:32:27
1294	Anand	IND	Gujarat	110266	2014-03-22 22:32:04
1295	Mango	IND	Jharkhand	110024	2007-01-06 16:22:54
1296	Santipur	IND	West Bengali	109956	2009-02-12 04:33:48
1297	Bhind	IND	Madhya Pradesh	109755	2006-01-27 20:19:20
1298	Gondiya	IND	Maharashtra	109470	2006-03-04 07:41:01
1299	Tiruvannamalai	IND	Tamil Nadu	109196	2006-08-27 16:25:51
1300	Yeotmal (Yavatmal)	IND	Maharashtra	108578	2009-12-20 16:32:27
1301	Kulti-Barakar	IND	West Bengali	108518	2005-04-09 22:35:28
1302	Moga	IND	Punjab	108304	2001-09-22 12:03:57
1303	Shivapuri	IND	Madhya Pradesh	108277	2008-05-21 05:32:59
1304	Bidar	IND	Karnataka	108016	2002-09-06 17:37:31
1305	Guntakal	IND	Andhra Pradesh	107592	2008-10-16 13:59:34
1306	Unnao	IND	Uttar Pradesh	107425	2008-05-22 06:19:24
1307	Barasat	IND	West Bengali	107365	2006-06-23 13:08:40
1308	Tambaram	IND	Tamil Nadu	107187	2006-07-12 14:41:53
1309	Abohar	IND	Punjab	107163	2005-04-11 11:42:53
1310	Pilibhit	IND	Uttar Pradesh	106605	2011-07-20 07:39:15
1311	Valparai	IND	Tamil Nadu	106523	2011-10-11 23:08:54
1312	Gonda	IND	Uttar Pradesh	106078	2003-12-23 23:46:07
1313	Surendranagar	IND	Gujarat	105973	2007-12-21 23:54:08
1314	Qutubullapur	IND	Andhra Pradesh	105380	2002-10-14 22:49:47
1315	Beawar	IND	Rajasthan	105363	2010-10-25 01:14:32
1316	Hindupur	IND	Andhra Pradesh	104651	2002-01-09 03:17:04
1317	Gandhidham	IND	Gujarat	104585	2001-04-16 13:50:02
1318	Haldwani-cum-Kathgodam	IND	Uttaranchal	104195	2004-02-25 09:02:43
1319	Tellicherry (Thalassery)	IND	Kerala	103579	2002-05-02 03:16:32
1320	Wardha	IND	Maharashtra	102985	2002-05-23 16:49:30
1321	Rishra	IND	West Bengali	102649	2011-12-18 13:40:55
1322	Bhuj	IND	Gujarat	102176	2010-10-12 10:33:25
1323	Modinagar	IND	Uttar Pradesh	101660	2008-01-26 23:52:25
1324	Gudivada	IND	Andhra Pradesh	101656	2006-08-02 14:10:22
1325	Basirhat	IND	West Bengali	101409	2011-09-04 05:02:46
1326	Uttarpara-Kotrung	IND	West Bengali	100867	2012-02-20 22:35:27
1327	Ongole	IND	Andhra Pradesh	100836	2008-11-16 15:27:57
1328	North Barrackpur	IND	West Bengali	100513	2013-02-01 07:41:03
1329	Guna	IND	Madhya Pradesh	100490	2009-07-14 16:33:22
1330	Haldia	IND	West Bengali	100347	2003-02-24 15:25:04
1331	Habra	IND	West Bengali	100223	2009-10-13 15:17:45
1332	Kanchrapara	IND	West Bengali	100194	2012-04-10 23:18:06
1333	Tonk	IND	Rajasthan	100079	2014-03-14 17:03:06
1334	Champdani	IND	West Bengali	98818	2003-07-11 11:10:35
1335	Orai	IND	Uttar Pradesh	98640	2013-03-12 16:38:52
1336	Pudukkottai	IND	Tamil Nadu	98619	2004-06-08 08:02:51
1337	Sasaram	IND	Bihar	98220	2002-08-07 21:23:16
1338	Hazaribag	IND	Jharkhand	97712	2001-02-13 09:54:06
1339	Palayankottai	IND	Tamil Nadu	97662	2001-06-15 20:12:32
1340	Banda	IND	Uttar Pradesh	97227	2014-10-02 17:04:49
1341	Godhra	IND	Gujarat	96813	2006-08-17 16:02:03
1342	Hospet	IND	Karnataka	96322	2004-05-24 20:04:02
1343	Ashoknagar-Kalyangarh	IND	West Bengali	96315	2003-02-11 02:21:09
1344	Achalpur	IND	Maharashtra	96216	2008-09-02 15:41:56
1345	Patan	IND	Gujarat	96109	2011-11-08 21:51:26
1346	Mandasor	IND	Madhya Pradesh	95758	2009-01-13 19:23:57
1347	Damoh	IND	Madhya Pradesh	95661	2013-01-27 11:21:49
1348	Satara	IND	Maharashtra	95133	2003-03-23 23:34:33
1349	Meerut Cantonment	IND	Uttar Pradesh	94876	2006-11-02 22:57:51
1350	Dehri	IND	Bihar	94526	2001-09-03 08:16:36
1351	Delhi Cantonment	IND	Delhi	94326	2013-01-27 06:09:28
1352	Chhindwara	IND	Madhya Pradesh	93731	2005-06-06 08:53:52
1353	Bansberia	IND	West Bengali	93447	2013-04-13 01:37:21
1354	Nagaon	IND	Assam	93350	2001-11-05 04:54:20
1355	Kanpur Cantonment	IND	Uttar Pradesh	93109	2009-10-12 15:03:41
1356	Vidisha	IND	Madhya Pradesh	92917	2012-02-25 12:09:43
1357	Bettiah	IND	Bihar	92583	2010-09-24 21:28:03
1358	Purulia	IND	Jharkhand	92574	2009-02-17 15:06:10
1359	Hassan	IND	Karnataka	90803	2004-01-26 01:34:20
1360	Ambala Sadar	IND	Haryana	90712	2004-12-27 07:19:35
1361	Baidyabati	IND	West Bengali	90601	2009-01-27 09:11:09
1362	Morvi	IND	Gujarat	90357	2013-06-20 03:28:53
1363	Raigarh	IND	Chhatisgarh	89166	2014-09-28 08:16:34
1364	Vejalpur	IND	Gujarat	89053	2011-07-15 16:23:46
1365	Baghdad	IRQ	Baghdad	4336000	2005-07-26 02:57:54
1366	Mosul	IRQ	Ninawa	879000	2011-03-24 04:33:45
1367	Irbil	IRQ	Irbil	485968	2013-04-04 09:46:48
1368	Kirkuk	IRQ	al-Tamim	418624	2012-04-15 04:49:48
1369	Basra	IRQ	Basra	406296	2011-03-02 23:58:03
1370	al-Sulaymaniya	IRQ	al-Sulaymaniya	364096	2002-07-28 02:17:03
1371	al-Najaf	IRQ	al-Najaf	309010	2010-06-14 17:46:45
1372	Karbala	IRQ	Karbala	296705	2007-12-15 17:21:53
1373	al-Hilla	IRQ	Babil	268834	2003-02-10 20:07:58
1374	al-Nasiriya	IRQ	DhiQar	265937	2010-04-27 01:04:29
1375	al-Amara	IRQ	Maysan	208797	2012-09-04 09:48:17
1376	al-Diwaniya	IRQ	al-Qadisiya	196519	2012-01-10 11:23:10
1377	al-Ramadi	IRQ	al-Anbar	192556	2004-11-21 05:34:51
1378	al-Kut	IRQ	Wasit	183183	2011-01-01 12:16:42
1379	Baquba	IRQ	Diyala	114516	2007-08-16 23:23:49
1380	Teheran	IRN	Teheran	6758845	2004-01-27 03:48:38
1381	Mashhad	IRN	Khorasan	1887405	2012-08-07 01:03:19
1382	Esfahan	IRN	Esfahan	1266072	2007-08-26 10:53:21
1383	Tabriz	IRN	East Azerbaidzan	1191043	2008-11-22 07:52:23
1384	Shiraz	IRN	Fars	1053025	2005-08-23 13:27:33
1385	Karaj	IRN	Teheran	940968	2005-10-27 23:08:46
1386	Ahvaz	IRN	Khuzestan	804980	2005-12-26 05:45:12
1387	Qom	IRN	Qom	777677	2004-11-03 07:01:57
1388	Kermanshah	IRN	Kermanshah	692986	2006-11-25 13:19:26
1389	Urmia	IRN	West Azerbaidzan	435200	2006-12-13 10:34:36
1390	Zahedan	IRN	Sistan va Baluchesta	419518	2002-04-07 20:25:47
1391	Rasht	IRN	Gilan	417748	2002-04-08 08:18:30
1392	Hamadan	IRN	Hamadan	401281	2007-08-25 14:29:43
1393	Kerman	IRN	Kerman	384991	2009-01-25 19:16:25
1394	Arak	IRN	Markazi	380755	2012-03-10 07:36:31
1395	Ardebil	IRN	Ardebil	340386	2008-10-09 23:57:07
1396	Yazd	IRN	Yazd	326776	2002-07-20 23:25:20
1397	Qazvin	IRN	Qazvin	291117	2002-03-01 01:35:45
1398	Zanjan	IRN	Zanjan	286295	2006-02-12 04:14:38
1399	Sanandaj	IRN	Kordestan	277808	2013-08-15 15:43:03
1400	Bandar-e-Abbas	IRN	Hormozgan	273578	2003-09-15 13:27:50
1401	Khorramabad	IRN	Lorestan	272815	2005-06-10 16:37:01
1402	Eslamshahr	IRN	Teheran	265450	2005-07-11 17:12:27
1403	Borujerd	IRN	Lorestan	217804	2010-04-10 19:36:16
1404	Abadan	IRN	Khuzestan	206073	2007-04-10 17:28:33
1405	Dezful	IRN	Khuzestan	202639	2003-07-04 15:08:14
1406	Kashan	IRN	Esfahan	201372	2007-08-21 01:10:28
1407	Sari	IRN	Mazandaran	195882	2012-10-28 15:25:45
1408	Gorgan	IRN	Golestan	188710	2013-01-09 03:03:06
1409	Najafabad	IRN	Esfahan	178498	2009-10-19 02:41:37
1410	Sabzevar	IRN	Khorasan	170738	2004-01-04 15:18:12
1411	Khomeynishahr	IRN	Esfahan	165888	2002-07-17 23:46:24
1412	Amol	IRN	Mazandaran	159092	2012-02-17 01:18:34
1413	Neyshabur	IRN	Khorasan	158847	2001-10-03 23:52:51
1414	Babol	IRN	Mazandaran	158346	2007-08-11 09:32:04
1415	Khoy	IRN	West Azerbaidzan	148944	2007-12-16 09:49:04
1416	Malayer	IRN	Hamadan	144373	2011-02-09 01:40:48
1417	Bushehr	IRN	Bushehr	143641	2012-04-22 02:14:02
1418	Qaemshahr	IRN	Mazandaran	143286	2001-09-01 17:22:54
1419	Qarchak	IRN	Teheran	142690	2004-06-06 02:52:23
1420	Qods	IRN	Teheran	138278	2002-06-23 20:51:30
1421	Sirjan	IRN	Kerman	135024	2009-12-26 04:39:52
1422	Bojnurd	IRN	Khorasan	134835	2004-02-22 16:30:33
1423	Maragheh	IRN	East Azerbaidzan	132318	2002-08-26 21:54:37
1424	Birjand	IRN	Khorasan	127608	2012-12-02 12:55:25
1425	Ilam	IRN	Ilam	126346	2004-11-14 14:06:41
1426	Bukan	IRN	West Azerbaidzan	120020	2004-09-15 12:20:54
1427	Masjed-e-Soleyman	IRN	Khuzestan	116883	2011-07-17 02:15:01
1428	Saqqez	IRN	Kordestan	115394	2001-11-07 12:33:31
1429	Gonbad-e Qabus	IRN	Mazandaran	111253	2003-12-28 19:58:44
1430	Saveh	IRN	Qom	111245	2011-11-23 11:48:56
1431	Mahabad	IRN	West Azerbaidzan	107799	2012-05-28 13:17:09
1432	Varamin	IRN	Teheran	107233	2014-09-14 02:53:23
1433	Andimeshk	IRN	Khuzestan	106923	2014-04-12 16:04:29
1434	Khorramshahr	IRN	Khuzestan	105636	2006-05-13 03:56:42
1435	Shahrud	IRN	Semnan	104765	2002-10-18 09:39:37
1436	Marv Dasht	IRN	Fars	103579	2014-01-28 14:32:32
1437	Zabol	IRN	Sistan va Baluchesta	100887	2008-10-20 17:46:16
1438	Shahr-e Kord	IRN	Chaharmahal va Bakht	100477	2012-08-19 13:33:57
1439	Bandar-e Anzali	IRN	Gilan	98500	2004-08-10 06:41:43
1440	Rafsanjan	IRN	Kerman	98300	2010-07-24 07:04:20
1441	Marand	IRN	East Azerbaidzan	96400	2009-01-28 17:47:45
1442	Torbat-e Heydariyeh	IRN	Khorasan	94600	2007-11-08 17:26:10
1443	Jahrom	IRN	Fars	94200	2003-07-13 04:41:12
1444	Semnan	IRN	Semnan	91045	2005-09-10 07:02:02
1445	Miandoab	IRN	West Azerbaidzan	90100	2005-02-05 09:39:10
1446	Qomsheh	IRN	Esfahan	89800	2011-01-26 23:49:50
1447	Dublin	IRL	Leinster	481854	2007-12-28 17:19:15
1448	Cork	IRL	Munster	127187	2011-06-05 23:05:56
1449	Reykjavík	ISL	Höfuðborgarsvæði	109184	2011-01-06 08:11:11
1450	Jerusalem	ISR	Jerusalem	633700	2014-12-27 04:32:33
1451	Tel Aviv-Jaffa	ISR	Tel Aviv	348100	2013-01-08 13:33:16
1452	Haifa	ISR	Haifa	265700	2009-05-11 03:51:31
1453	Rishon Le Ziyyon	ISR	Ha Merkaz	188200	2013-08-19 15:06:39
1454	Beerseba	ISR	Ha Darom	163700	2006-04-26 20:23:17
1455	Holon	ISR	Tel Aviv	163100	2012-12-06 21:41:04
1456	Petah Tiqwa	ISR	Ha Merkaz	159400	2002-01-14 12:45:32
1457	Ashdod	ISR	Ha Darom	155800	2012-07-13 22:52:45
1458	Netanya	ISR	Ha Merkaz	154900	2011-07-16 11:20:15
1459	Bat Yam	ISR	Tel Aviv	137000	2013-04-24 02:22:26
1460	Bene Beraq	ISR	Tel Aviv	133900	2010-05-15 23:30:04
1461	Ramat Gan	ISR	Tel Aviv	126900	2013-12-27 13:13:51
1462	Ashqelon	ISR	Ha Darom	92300	2001-11-15 18:31:06
1463	Rehovot	ISR	Ha Merkaz	90300	2001-06-20 01:18:58
1464	Roma	ITA	Latium	2643581	2003-09-05 04:38:39
1465	Milano	ITA	Lombardia	1300977	2006-12-05 01:55:14
1466	Napoli	ITA	Campania	1002619	2005-05-15 04:52:05
1467	Torino	ITA	Piemonte	903705	2012-03-18 13:56:58
1468	Palermo	ITA	Sisilia	683794	2004-08-11 05:23:24
1469	Genova	ITA	Liguria	636104	2005-02-20 07:56:43
1470	Bologna	ITA	Emilia-Romagna	381161	2005-09-28 06:28:47
1471	Firenze	ITA	Toscana	376662	2005-10-06 12:14:51
1472	Catania	ITA	Sisilia	337862	2006-04-09 05:14:33
1473	Bari	ITA	Apulia	331848	2005-02-05 08:33:32
1474	Venezia	ITA	Veneto	277305	2012-12-28 08:18:09
1475	Messina	ITA	Sisilia	259156	2005-10-23 16:08:34
1476	Verona	ITA	Veneto	255268	2009-03-26 16:35:39
1477	Trieste	ITA	Friuli-Venezia Giuli	216459	2001-12-08 05:48:39
1478	Padova	ITA	Veneto	211391	2005-08-11 05:17:14
1479	Taranto	ITA	Apulia	208214	2006-04-05 09:33:01
1480	Brescia	ITA	Lombardia	191317	2005-10-16 01:51:03
1481	Reggio di Calabria	ITA	Calabria	179617	2005-12-20 21:23:43
1482	Modena	ITA	Emilia-Romagna	176022	2003-12-05 17:07:38
1483	Prato	ITA	Toscana	172473	2005-08-08 02:30:33
1484	Parma	ITA	Emilia-Romagna	168717	2013-11-27 23:09:43
1485	Cagliari	ITA	Sardinia	165926	2010-04-01 02:28:53
1486	Livorno	ITA	Toscana	161673	2002-08-14 14:40:33
1487	Perugia	ITA	Umbria	156673	2010-08-09 01:18:47
1488	Foggia	ITA	Apulia	154891	2012-12-17 18:35:27
1489	Reggio nell´ Emilia	ITA	Emilia-Romagna	143664	2009-12-26 07:08:16
1490	Salerno	ITA	Campania	142055	2008-10-13 17:21:11
1491	Ravenna	ITA	Emilia-Romagna	138418	2009-03-03 21:41:17
1492	Ferrara	ITA	Emilia-Romagna	132127	2005-01-25 10:09:26
1493	Rimini	ITA	Emilia-Romagna	131062	2004-05-19 03:10:19
1494	Syrakusa	ITA	Sisilia	126282	2011-05-20 15:17:04
1495	Sassari	ITA	Sardinia	120803	2003-01-01 09:05:38
1496	Monza	ITA	Lombardia	119516	2001-03-05 07:50:45
1497	Bergamo	ITA	Lombardia	117837	2011-12-02 07:06:12
1498	Pescara	ITA	Abruzzit	115698	2010-02-27 01:22:45
1499	Latina	ITA	Latium	114099	2011-03-22 21:23:39
1500	Vicenza	ITA	Veneto	109738	2003-03-26 05:06:37
1501	Terni	ITA	Umbria	107770	2004-11-11 12:29:27
1502	Forlì	ITA	Emilia-Romagna	107475	2003-03-21 18:27:18
1503	Trento	ITA	Trentino-Alto Adige	104906	2009-11-06 04:52:32
1504	Novara	ITA	Piemonte	102037	2014-10-25 13:33:55
1505	Piacenza	ITA	Emilia-Romagna	98384	2010-12-21 15:20:36
1506	Ancona	ITA	Marche	98329	2001-07-28 12:08:29
1507	Lecce	ITA	Apulia	98208	2003-05-23 10:08:34
1508	Bolzano	ITA	Trentino-Alto Adige	97232	2009-01-19 21:56:29
1509	Catanzaro	ITA	Calabria	96700	2010-10-22 17:03:24
1510	La Spezia	ITA	Liguria	95504	2011-11-20 13:49:55
1511	Udine	ITA	Friuli-Venezia Giuli	94932	2008-11-21 09:21:11
1512	Torre del Greco	ITA	Campania	94505	2001-08-15 13:34:05
1513	Andria	ITA	Apulia	94443	2014-04-23 14:41:23
1514	Brindisi	ITA	Apulia	93454	2008-02-18 15:28:10
1515	Giugliano in Campania	ITA	Campania	93286	2003-06-01 20:47:44
1516	Pisa	ITA	Toscana	92379	2006-01-27 08:33:11
1517	Barletta	ITA	Apulia	91904	2002-02-25 17:29:29
1518	Arezzo	ITA	Toscana	91729	2012-07-17 13:35:51
1519	Alessandria	ITA	Piemonte	90289	2014-12-28 23:22:09
1520	Cesena	ITA	Emilia-Romagna	89852	2011-12-08 07:04:38
1521	Pesaro	ITA	Marche	88987	2003-03-24 16:30:49
1522	Dili	TMP	Dili	47900	2005-10-16 01:55:52
1523	Wien	AUT	Wien	1608144	2001-03-09 10:04:50
1524	Graz	AUT	Steiermark	240967	2009-08-15 23:44:26
1525	Linz	AUT	North Austria	188022	2009-05-01 06:18:04
1526	Salzburg	AUT	Salzburg	144247	2008-11-04 07:07:07
1527	Innsbruck	AUT	Tiroli	111752	2006-04-24 15:56:07
1528	Klagenfurt	AUT	Kärnten	91141	2005-05-13 23:04:33
1529	Spanish Town	JAM	St. Catherine	110379	2010-12-21 19:56:23
1530	Kingston	JAM	St. Andrew	103962	2007-09-08 04:12:30
1531	Portmore	JAM	St. Andrew	99799	2003-04-06 23:57:19
1532	Tokyo	JPN	Tokyo-to	7980230	2011-03-18 16:59:41
1533	Jokohama [Yokohama]	JPN	Kanagawa	3339594	2007-11-18 01:59:03
1534	Osaka	JPN	Osaka	2595674	2005-05-24 07:51:17
1535	Nagoya	JPN	Aichi	2154376	2009-11-09 21:46:19
1536	Sapporo	JPN	Hokkaido	1790886	2014-09-23 11:16:07
1537	Kioto	JPN	Kyoto	1461974	2014-10-05 13:26:35
1538	Kobe	JPN	Hyogo	1425139	2014-12-01 08:42:05
1539	Fukuoka	JPN	Fukuoka	1308379	2005-07-10 11:32:23
1540	Kawasaki	JPN	Kanagawa	1217359	2014-07-18 01:22:28
1541	Hiroshima	JPN	Hiroshima	1119117	2010-01-22 15:09:43
1542	Kitakyushu	JPN	Fukuoka	1016264	2002-10-15 20:06:51
1543	Sendai	JPN	Miyagi	989975	2003-01-16 15:59:02
1544	Chiba	JPN	Chiba	863930	2003-03-02 10:32:58
1545	Sakai	JPN	Osaka	797735	2012-07-21 11:13:51
1546	Kumamoto	JPN	Kumamoto	656734	2012-03-13 20:48:19
1547	Okayama	JPN	Okayama	624269	2009-03-13 09:04:17
1548	Sagamihara	JPN	Kanagawa	586300	2003-04-21 18:25:08
1549	Hamamatsu	JPN	Shizuoka	568796	2001-12-07 13:30:46
1550	Kagoshima	JPN	Kagoshima	549977	2005-04-02 19:32:52
1551	Funabashi	JPN	Chiba	545299	2003-06-13 09:40:49
1552	Higashiosaka	JPN	Osaka	517785	2010-09-09 11:32:35
1553	Hachioji	JPN	Tokyo-to	513451	2007-04-21 14:19:04
1554	Niigata	JPN	Niigata	497464	2003-09-11 14:10:18
1555	Amagasaki	JPN	Hyogo	481434	2003-05-05 05:15:11
1556	Himeji	JPN	Hyogo	475167	2014-02-05 01:08:03
1557	Shizuoka	JPN	Shizuoka	473854	2011-07-18 04:05:42
1558	Urawa	JPN	Saitama	469675	2006-05-06 07:19:25
1559	Matsuyama	JPN	Ehime	466133	2010-05-11 23:59:53
1560	Matsudo	JPN	Chiba	461126	2009-03-21 13:50:11
1561	Kanazawa	JPN	Ishikawa	455386	2011-07-15 12:09:23
1562	Kawaguchi	JPN	Saitama	452155	2001-03-12 14:05:15
1563	Ichikawa	JPN	Chiba	441893	2002-03-28 13:02:39
1564	Omiya	JPN	Saitama	441649	2001-08-27 18:49:08
1565	Utsunomiya	JPN	Tochigi	440353	2009-06-09 06:11:10
1566	Oita	JPN	Oita	433401	2006-05-01 10:35:25
1567	Nagasaki	JPN	Nagasaki	432759	2007-05-26 14:57:16
1568	Yokosuka	JPN	Kanagawa	430200	2001-06-26 15:26:32
1569	Kurashiki	JPN	Okayama	425103	2001-10-12 23:45:03
1570	Gifu	JPN	Gifu	408007	2001-12-11 22:28:31
1571	Hirakata	JPN	Osaka	403151	2008-07-20 23:18:47
1572	Nishinomiya	JPN	Hyogo	397618	2014-04-04 12:47:06
1573	Toyonaka	JPN	Osaka	396689	2004-06-23 12:03:21
1574	Wakayama	JPN	Wakayama	391233	2006-08-28 15:17:56
1575	Fukuyama	JPN	Hiroshima	376921	2006-08-10 04:15:03
1576	Fujisawa	JPN	Kanagawa	372840	2009-03-14 14:44:01
1577	Asahikawa	JPN	Hokkaido	364813	2011-10-16 08:33:18
1578	Machida	JPN	Tokyo-to	364197	2003-05-27 03:57:58
1579	Nara	JPN	Nara	362812	2006-09-26 19:53:01
1580	Takatsuki	JPN	Osaka	361747	2005-01-26 09:38:24
1581	Iwaki	JPN	Fukushima	361737	2014-08-01 03:26:02
1582	Nagano	JPN	Nagano	361391	2010-01-06 17:04:45
1583	Toyohashi	JPN	Aichi	360066	2006-02-07 12:51:33
1584	Toyota	JPN	Aichi	346090	2004-04-08 14:56:30
1585	Suita	JPN	Osaka	345750	2014-04-20 06:16:08
1586	Takamatsu	JPN	Kagawa	332471	2003-09-16 18:58:04
1587	Koriyama	JPN	Fukushima	330335	2013-09-19 23:09:21
1588	Okazaki	JPN	Aichi	328711	2007-05-08 12:54:28
1589	Kawagoe	JPN	Saitama	327211	2004-11-26 14:41:30
1590	Tokorozawa	JPN	Saitama	325809	2006-01-04 22:55:19
1591	Toyama	JPN	Toyama	325790	2013-09-26 17:29:15
1592	Kochi	JPN	Kochi	324710	2013-09-27 20:09:06
1593	Kashiwa	JPN	Chiba	320296	2004-12-04 01:12:15
1594	Akita	JPN	Akita	314440	2012-09-09 08:15:07
1595	Miyazaki	JPN	Miyazaki	303784	2001-02-23 07:44:41
1596	Koshigaya	JPN	Saitama	301446	2004-11-20 15:22:27
1597	Naha	JPN	Okinawa	299851	2013-07-11 01:32:09
1598	Aomori	JPN	Aomori	295969	2007-06-21 23:58:59
1599	Hakodate	JPN	Hokkaido	294788	2008-08-16 07:34:52
1600	Akashi	JPN	Hyogo	292253	2001-09-05 18:15:19
1601	Yokkaichi	JPN	Mie	288173	2014-11-18 15:48:18
1602	Fukushima	JPN	Fukushima	287525	2005-08-22 12:02:10
1603	Morioka	JPN	Iwate	287353	2012-01-26 02:23:58
1604	Maebashi	JPN	Gumma	284473	2007-05-05 06:09:41
1605	Kasugai	JPN	Aichi	282348	2010-01-09 15:32:18
1606	Otsu	JPN	Shiga	282070	2009-09-06 20:34:17
1607	Ichihara	JPN	Chiba	279280	2007-04-03 14:49:53
1608	Yao	JPN	Osaka	276421	2001-08-02 16:46:45
1609	Ichinomiya	JPN	Aichi	270828	2002-09-18 23:23:13
1610	Tokushima	JPN	Tokushima	269649	2010-10-07 15:17:43
1611	Kakogawa	JPN	Hyogo	266281	2004-06-06 04:50:37
1612	Ibaraki	JPN	Osaka	261020	2012-11-24 11:27:54
1613	Neyagawa	JPN	Osaka	257315	2012-04-24 04:09:34
1614	Shimonoseki	JPN	Yamaguchi	257263	2014-05-24 22:16:24
1615	Yamagata	JPN	Yamagata	255617	2013-06-28 23:45:42
1616	Fukui	JPN	Fukui	254818	2014-10-25 19:58:25
1617	Hiratsuka	JPN	Kanagawa	254207	2008-11-11 08:05:51
1618	Mito	JPN	Ibaragi	246559	2009-05-28 06:30:58
1619	Sasebo	JPN	Nagasaki	244240	2001-09-01 05:23:33
1620	Hachinohe	JPN	Aomori	242979	2013-11-23 03:42:47
1621	Takasaki	JPN	Gumma	239124	2006-08-09 21:20:11
1622	Shimizu	JPN	Shizuoka	239123	2008-08-28 06:25:48
1623	Kurume	JPN	Fukuoka	235611	2014-04-17 05:19:09
1624	Fuji	JPN	Shizuoka	231527	2013-04-27 18:01:37
1625	Soka	JPN	Saitama	222768	2014-11-19 12:18:05
1626	Fuchu	JPN	Tokyo-to	220576	2009-03-16 09:29:53
1627	Chigasaki	JPN	Kanagawa	216015	2001-11-24 01:22:38
1628	Atsugi	JPN	Kanagawa	212407	2004-03-02 08:22:32
1629	Numazu	JPN	Shizuoka	211382	2010-01-13 18:37:13
1630	Ageo	JPN	Saitama	209442	2013-12-05 19:11:36
1631	Yamato	JPN	Kanagawa	208234	2012-06-10 03:23:20
1632	Matsumoto	JPN	Nagano	206801	2004-07-05 10:44:17
1633	Kure	JPN	Hiroshima	206504	2006-01-11 11:45:44
1634	Takarazuka	JPN	Hyogo	205993	2013-06-20 07:21:08
1635	Kasukabe	JPN	Saitama	201838	2008-06-02 19:51:15
1636	Chofu	JPN	Tokyo-to	201585	2006-10-08 08:37:04
1637	Odawara	JPN	Kanagawa	200171	2009-07-23 04:16:28
1638	Kofu	JPN	Yamanashi	199753	2013-02-10 07:22:53
1639	Kushiro	JPN	Hokkaido	197608	2009-12-01 20:22:08
1640	Kishiwada	JPN	Osaka	197276	2002-09-22 20:11:02
1641	Hitachi	JPN	Ibaragi	196622	2005-05-26 14:38:30
1642	Nagaoka	JPN	Niigata	192407	2006-06-14 20:30:17
1643	Itami	JPN	Hyogo	190886	2001-02-14 13:03:02
1644	Uji	JPN	Kyoto	188735	2005-07-07 23:01:46
1645	Suzuka	JPN	Mie	184061	2011-09-02 15:49:54
1646	Hirosaki	JPN	Aomori	177522	2001-02-22 13:13:04
1647	Ube	JPN	Yamaguchi	175206	2012-07-05 17:20:15
1648	Kodaira	JPN	Tokyo-to	174984	2014-01-08 13:49:26
1649	Takaoka	JPN	Toyama	174380	2013-10-14 19:22:13
1650	Obihiro	JPN	Hokkaido	173685	2004-05-04 17:09:55
1651	Tomakomai	JPN	Hokkaido	171958	2012-12-26 18:57:29
1652	Saga	JPN	Saga	170034	2001-01-28 18:01:20
1653	Sakura	JPN	Chiba	168072	2014-09-22 19:50:28
1654	Kamakura	JPN	Kanagawa	167661	2010-06-08 21:14:58
1655	Mitaka	JPN	Tokyo-to	167268	2005-04-27 12:25:35
1656	Izumi	JPN	Osaka	166979	2007-05-19 17:52:38
1657	Hino	JPN	Tokyo-to	166770	2005-03-11 14:35:54
1658	Hadano	JPN	Kanagawa	166512	2014-11-10 17:19:16
1659	Ashikaga	JPN	Tochigi	165243	2007-05-20 10:08:08
1660	Tsu	JPN	Mie	164543	2007-05-08 01:09:55
1661	Sayama	JPN	Saitama	162472	2001-03-23 10:40:18
1662	Yachiyo	JPN	Chiba	161222	2007-02-02 14:37:08
1663	Tsukuba	JPN	Ibaragi	160768	2008-01-25 04:25:57
1664	Tachikawa	JPN	Tokyo-to	159430	2011-05-02 21:59:01
1665	Kumagaya	JPN	Saitama	157171	2001-11-20 16:22:41
1666	Moriguchi	JPN	Osaka	155941	2001-04-18 17:29:39
1667	Otaru	JPN	Hokkaido	155784	2007-11-14 16:42:26
1668	Anjo	JPN	Aichi	153823	2004-02-20 20:59:43
1669	Narashino	JPN	Chiba	152849	2008-01-25 01:20:56
1670	Oyama	JPN	Tochigi	152820	2002-07-13 23:05:43
1671	Ogaki	JPN	Gifu	151758	2011-11-09 14:28:25
1672	Matsue	JPN	Shimane	149821	2011-06-06 10:34:11
1673	Kawanishi	JPN	Hyogo	149794	2009-05-19 03:06:54
1674	Hitachinaka	JPN	Tokyo-to	148006	2007-03-06 07:06:07
1675	Niiza	JPN	Saitama	147744	2004-08-16 23:50:46
1676	Nagareyama	JPN	Chiba	147738	2003-07-20 07:18:13
1677	Tottori	JPN	Tottori	147523	2004-05-09 11:58:45
1678	Tama	JPN	Ibaragi	146712	2004-07-05 02:13:56
1679	Iruma	JPN	Saitama	145922	2006-07-06 12:59:58
1680	Ota	JPN	Gumma	145317	2003-01-19 21:40:25
1681	Omuta	JPN	Fukuoka	142889	2011-06-07 17:49:17
1682	Komaki	JPN	Aichi	139827	2006-09-07 12:47:33
1683	Ome	JPN	Tokyo-to	139216	2001-12-27 20:13:06
1684	Kadoma	JPN	Osaka	138953	2012-12-13 07:45:08
1685	Yamaguchi	JPN	Yamaguchi	138210	2003-06-09 09:59:15
1686	Higashimurayama	JPN	Tokyo-to	136970	2013-01-27 16:23:41
1687	Yonago	JPN	Tottori	136461	2002-09-16 16:42:40
1688	Matsubara	JPN	Osaka	135010	2001-10-14 08:20:32
1689	Musashino	JPN	Tokyo-to	134426	2013-06-05 05:07:23
1690	Tsuchiura	JPN	Ibaragi	134072	2011-04-12 23:13:59
1691	Joetsu	JPN	Niigata	133505	2001-06-02 13:26:40
1692	Miyakonojo	JPN	Miyazaki	133183	2009-11-14 21:13:35
1693	Misato	JPN	Saitama	132957	2012-06-23 08:08:36
1694	Kakamigahara	JPN	Gifu	131831	2008-07-21 05:22:32
1695	Daito	JPN	Osaka	130594	2014-10-13 11:59:18
1696	Seto	JPN	Aichi	130470	2007-11-19 23:53:46
1697	Kariya	JPN	Aichi	127969	2006-03-07 06:18:24
1698	Urayasu	JPN	Chiba	127550	2012-07-28 14:16:23
1699	Beppu	JPN	Oita	127486	2003-09-01 08:33:15
1700	Niihama	JPN	Ehime	127207	2010-02-15 14:46:34
1701	Minoo	JPN	Osaka	127026	2009-07-02 22:05:04
1702	Fujieda	JPN	Shizuoka	126897	2006-12-06 11:37:03
1703	Abiko	JPN	Chiba	126670	2005-03-16 20:49:28
1704	Nobeoka	JPN	Miyazaki	125547	2006-10-08 23:19:39
1705	Tondabayashi	JPN	Osaka	125094	2002-12-06 03:39:09
1706	Ueda	JPN	Nagano	124217	2012-07-08 13:59:34
1707	Kashihara	JPN	Nara	124013	2010-07-11 16:23:05
1708	Matsusaka	JPN	Mie	123582	2007-05-16 09:43:15
1709	Isesaki	JPN	Gumma	123285	2013-06-17 01:29:06
1710	Zama	JPN	Kanagawa	122046	2001-04-07 04:48:57
1711	Kisarazu	JPN	Chiba	121967	2006-10-01 07:33:48
1712	Noda	JPN	Chiba	121030	2013-06-01 13:04:02
1713	Ishinomaki	JPN	Miyagi	120963	2012-09-10 19:47:33
1714	Fujinomiya	JPN	Shizuoka	119714	2008-08-16 09:17:41
1715	Kawachinagano	JPN	Osaka	119666	2013-05-21 23:06:24
1716	Imabari	JPN	Ehime	119357	2002-04-25 22:15:14
1717	Aizuwakamatsu	JPN	Fukushima	119287	2011-07-28 05:58:02
1718	Higashihiroshima	JPN	Hiroshima	119166	2008-04-13 13:33:28
1719	Habikino	JPN	Osaka	118968	2012-04-10 07:26:04
1720	Ebetsu	JPN	Hokkaido	118805	2014-06-22 23:02:53
1721	Hofu	JPN	Yamaguchi	118751	2010-10-12 05:16:20
1722	Kiryu	JPN	Gumma	118326	2002-09-06 19:59:48
1723	Okinawa	JPN	Okinawa	117748	2006-07-11 02:36:11
1724	Yaizu	JPN	Shizuoka	117258	2006-07-02 04:52:56
1725	Toyokawa	JPN	Aichi	115781	2008-03-01 22:31:33
1726	Ebina	JPN	Kanagawa	115571	2008-04-19 20:30:28
1727	Asaka	JPN	Saitama	114815	2006-09-18 05:41:58
1728	Higashikurume	JPN	Tokyo-to	111666	2014-03-04 12:49:06
1729	Ikoma	JPN	Nara	111645	2011-11-11 09:11:21
1730	Kitami	JPN	Hokkaido	111295	2006-07-12 23:22:24
1731	Koganei	JPN	Tokyo-to	110969	2001-08-19 05:31:45
1732	Iwatsuki	JPN	Saitama	110034	2008-01-17 04:05:16
1733	Mishima	JPN	Shizuoka	109699	2007-06-07 23:56:50
1734	Handa	JPN	Aichi	108600	2007-10-09 01:09:58
1735	Muroran	JPN	Hokkaido	108275	2014-03-02 17:18:16
1736	Komatsu	JPN	Ishikawa	107937	2001-04-04 23:26:48
1737	Yatsushiro	JPN	Kumamoto	107661	2001-06-18 18:43:24
1738	Iida	JPN	Nagano	107583	2002-04-22 06:42:05
1739	Tokuyama	JPN	Yamaguchi	107078	2003-11-03 05:31:59
1740	Kokubunji	JPN	Tokyo-to	106996	2013-01-11 22:37:14
1741	Akishima	JPN	Tokyo-to	106914	2003-06-01 03:47:37
1742	Iwakuni	JPN	Yamaguchi	106647	2014-02-25 04:22:53
1743	Kusatsu	JPN	Shiga	106232	2011-08-26 14:53:40
1744	Kuwana	JPN	Mie	106121	2002-01-01 15:36:12
1745	Sanda	JPN	Hyogo	105643	2011-03-21 03:08:58
1746	Hikone	JPN	Shiga	105508	2002-10-01 11:52:07
1747	Toda	JPN	Saitama	103969	2005-10-14 15:36:21
1748	Tajimi	JPN	Gifu	103171	2003-12-25 14:22:45
1749	Ikeda	JPN	Osaka	102710	2007-08-10 13:32:28
1750	Fukaya	JPN	Saitama	102156	2011-08-12 01:57:16
1751	Ise	JPN	Mie	101732	2013-04-20 04:56:40
1752	Sakata	JPN	Yamagata	101651	2004-10-20 09:45:50
1753	Kasuga	JPN	Fukuoka	101344	2004-04-22 05:17:25
1754	Kamagaya	JPN	Chiba	100821	2002-05-03 05:19:30
1755	Tsuruoka	JPN	Yamagata	100713	2006-04-13 11:51:04
1756	Hoya	JPN	Tokyo-to	100313	2013-05-14 16:58:16
1757	Nishio	JPN	Chiba	100032	2012-11-25 13:03:43
1758	Tokai	JPN	Aichi	99738	2004-08-07 04:31:46
1759	Inazawa	JPN	Aichi	98746	2007-10-19 19:59:39
1760	Sakado	JPN	Saitama	98221	2005-05-19 18:32:24
1761	Isehara	JPN	Kanagawa	98123	2006-10-10 22:03:10
1762	Takasago	JPN	Hyogo	97632	2006-07-13 19:15:11
1763	Fujimi	JPN	Saitama	96972	2014-02-01 05:50:30
1764	Urasoe	JPN	Okinawa	96002	2012-01-19 18:02:25
1765	Yonezawa	JPN	Yamagata	95592	2004-08-23 02:38:05
1766	Konan	JPN	Aichi	95521	2011-08-07 02:23:41
1767	Yamatokoriyama	JPN	Nara	95165	2013-11-17 23:25:50
1768	Maizuru	JPN	Kyoto	94784	2010-10-16 08:09:45
1769	Onomichi	JPN	Hiroshima	93756	2012-02-11 23:37:01
1770	Higashimatsuyama	JPN	Saitama	93342	2011-04-02 23:55:25
1771	Kimitsu	JPN	Chiba	93216	2004-01-28 22:39:12
1772	Isahaya	JPN	Nagasaki	93058	2005-02-14 04:25:42
1773	Kanuma	JPN	Tochigi	93053	2011-08-03 10:58:46
1774	Izumisano	JPN	Osaka	92583	2006-01-26 02:29:26
1775	Kameoka	JPN	Kyoto	92398	2008-12-11 22:10:02
1776	Mobara	JPN	Chiba	91664	2009-02-23 23:25:20
1777	Narita	JPN	Chiba	91470	2005-12-12 14:59:45
1778	Kashiwazaki	JPN	Niigata	91229	2014-02-10 13:47:49
1779	Tsuyama	JPN	Okayama	91170	2008-05-14 18:23:56
1780	Sanaa	YEM	Sanaa	503600	2007-04-28 05:39:28
1781	Aden	YEM	Aden	398300	2009-01-01 12:50:15
1782	Taizz	YEM	Taizz	317600	2006-01-07 09:11:36
1783	Hodeida	YEM	Hodeida	298500	2005-06-15 04:17:37
1784	al-Mukalla	YEM	Hadramawt	122400	2009-07-28 14:59:55
1785	Ibb	YEM	Ibb	103300	2002-08-10 18:53:55
1786	Amman	JOR	Amman	1000000	2008-06-19 06:43:37
1787	al-Zarqa	JOR	al-Zarqa	389815	2004-01-04 02:21:01
1788	Irbid	JOR	Irbid	231511	2002-05-17 04:49:58
1789	al-Rusayfa	JOR	al-Zarqa	137247	2006-06-02 03:52:31
1790	Wadi al-Sir	JOR	Amman	89104	2006-02-18 14:17:26
1791	Flying Fish Cove	CXR		700	2005-07-10 20:23:24
1792	Beograd	YUG	Central Serbia	1204000	2007-03-12 12:50:02
1793	Novi Sad	YUG	Vojvodina	179626	2008-09-09 14:33:36
1794	Ni	YUG	Central Serbia	175391	2003-08-04 02:34:36
1795	Pritina	YUG	Kosovo and Metohija	155496	2001-04-22 03:55:54
1796	Kragujevac	YUG	Central Serbia	147305	2004-12-04 06:48:30
1797	Podgorica	YUG	Montenegro	135000	2014-06-11 07:58:37
1798	Subotica	YUG	Vojvodina	100386	2009-10-19 06:55:28
1799	Prizren	YUG	Kosovo and Metohija	92303	2007-09-23 21:30:01
1800	Phnom Penh	KHM	Phnom Penh	570155	2003-07-15 11:06:57
1801	Battambang	KHM	Battambang	129800	2010-01-22 09:51:24
1802	Siem Reap	KHM	Siem Reap	105100	2005-12-03 01:10:34
1803	Douala	CMR	Littoral	1448300	2012-07-06 17:26:22
1804	Yaoundé	CMR	Centre	1372800	2011-03-23 06:16:20
1805	Garoua	CMR	Nord	177000	2006-11-17 22:06:22
1806	Maroua	CMR	Extrême-Nord	143000	2012-11-10 17:45:55
1807	Bamenda	CMR	Nord-Ouest	138000	2005-12-27 04:40:09
1808	Bafoussam	CMR	Ouest	131000	2014-02-09 14:33:09
1809	Nkongsamba	CMR	Littoral	112454	2007-02-24 03:49:24
1810	Montréal	CAN	Québec	1016376	2007-04-01 03:43:29
1811	Calgary	CAN	Alberta	768082	2006-12-11 07:18:13
1812	Toronto	CAN	Ontario	688275	2009-09-28 13:50:39
1813	North York	CAN	Ontario	622632	2010-01-24 20:05:35
1814	Winnipeg	CAN	Manitoba	618477	2007-12-06 23:02:54
1815	Edmonton	CAN	Alberta	616306	2001-08-12 22:28:18
1816	Mississauga	CAN	Ontario	608072	2012-02-16 18:57:08
1817	Scarborough	CAN	Ontario	594501	2005-02-08 07:25:37
1818	Vancouver	CAN	British Colombia	514008	2003-06-06 23:19:13
1819	Etobicoke	CAN	Ontario	348845	2011-02-12 17:31:17
1820	London	CAN	Ontario	339917	2003-12-26 03:32:43
1821	Hamilton	CAN	Ontario	335614	2013-04-21 07:57:15
1822	Ottawa	CAN	Ontario	335277	2014-03-08 15:31:56
1823	Laval	CAN	Québec	330393	2009-09-25 18:06:11
1824	Surrey	CAN	British Colombia	304477	2009-09-23 11:02:17
1825	Brampton	CAN	Ontario	296711	2013-02-17 18:20:55
1826	Windsor	CAN	Ontario	207588	2008-11-03 21:47:09
1827	Saskatoon	CAN	Saskatchewan	193647	2002-12-13 08:21:35
1828	Kitchener	CAN	Ontario	189959	2008-11-24 07:39:43
1829	Markham	CAN	Ontario	189098	2014-08-01 12:33:32
1830	Regina	CAN	Saskatchewan	180400	2003-11-17 23:46:23
1831	Burnaby	CAN	British Colombia	179209	2012-10-12 09:15:57
1832	Québec	CAN	Québec	167264	2002-04-11 17:27:10
1833	York	CAN	Ontario	154980	2004-01-28 17:16:40
1834	Richmond	CAN	British Colombia	148867	2011-11-25 11:08:09
1835	Vaughan	CAN	Ontario	147889	2014-09-05 19:42:59
1836	Burlington	CAN	Ontario	145150	2011-06-03 23:01:55
1837	Oshawa	CAN	Ontario	140173	2013-12-27 21:19:02
1838	Oakville	CAN	Ontario	139192	2003-06-20 02:59:34
1839	Saint Catharines	CAN	Ontario	136216	2001-01-28 06:04:16
1840	Longueuil	CAN	Québec	127977	2011-09-19 08:40:59
1841	Richmond Hill	CAN	Ontario	116428	2014-07-11 21:42:51
1842	Thunder Bay	CAN	Ontario	115913	2006-08-10 10:40:46
1843	Nepean	CAN	Ontario	115100	2007-03-24 22:33:50
1844	Cape Breton	CAN	Nova Scotia	114733	2006-03-22 03:33:37
1845	East York	CAN	Ontario	114034	2003-10-20 04:12:39
1846	Halifax	CAN	Nova Scotia	113910	2010-05-26 02:03:57
1847	Cambridge	CAN	Ontario	109186	2003-05-10 02:34:23
1848	Gloucester	CAN	Ontario	107314	2011-11-27 06:23:47
1849	Abbotsford	CAN	British Colombia	105403	2009-01-13 14:23:37
1850	Guelph	CAN	Ontario	103593	2012-04-23 23:20:35
1851	Saint John´s	CAN	Newfoundland	101936	2008-07-25 22:06:29
1852	Coquitlam	CAN	British Colombia	101820	2006-04-12 03:02:41
1853	Saanich	CAN	British Colombia	101388	2002-10-12 18:58:29
1854	Gatineau	CAN	Québec	100702	2010-09-01 04:43:06
1855	Delta	CAN	British Colombia	95411	2005-05-20 03:04:41
1856	Sudbury	CAN	Ontario	92686	2005-04-19 19:47:12
1857	Kelowna	CAN	British Colombia	89442	2006-02-20 08:48:35
1858	Barrie	CAN	Ontario	89269	2004-12-15 21:51:52
1859	Praia	CPV	São Tiago	94800	2011-03-01 05:23:43
1860	Almaty	KAZ	Almaty Qalasy	1129400	2014-08-06 07:46:17
1861	Qaraghandy	KAZ	Qaraghandy	436900	2001-03-13 13:50:57
1862	Shymkent	KAZ	South Kazakstan	360100	2011-12-08 01:27:07
1863	Taraz	KAZ	Taraz	330100	2006-03-21 18:23:31
1864	Astana	KAZ	Astana	311200	2003-06-04 03:33:51
1865	Öskemen	KAZ	East Kazakstan	311000	2010-04-23 22:12:46
1866	Pavlodar	KAZ	Pavlodar	300500	2010-03-17 01:51:22
1867	Semey	KAZ	East Kazakstan	269600	2001-03-07 04:36:14
1868	Aqtöbe	KAZ	Aqtöbe	253100	2004-04-22 13:54:44
1869	Qostanay	KAZ	Qostanay	221400	2001-08-23 17:50:46
1870	Petropavl	KAZ	North Kazakstan	203500	2013-04-14 06:41:43
1871	Oral	KAZ	West Kazakstan	195500	2013-06-07 19:43:11
1872	Temirtau	KAZ	Qaraghandy	170500	2004-01-11 12:31:35
1873	Qyzylorda	KAZ	Qyzylorda	157400	2002-07-03 09:41:18
1874	Aqtau	KAZ	Mangghystau	143400	2008-02-22 04:08:27
1875	Atyrau	KAZ	Atyrau	142500	2013-06-21 17:31:28
1876	Ekibastuz	KAZ	Pavlodar	127200	2012-10-17 05:32:15
1877	Kökshetau	KAZ	North Kazakstan	123400	2011-04-01 05:18:57
1878	Rudnyy	KAZ	Qostanay	109500	2010-08-13 04:42:49
1879	Taldyqorghan	KAZ	Almaty	98000	2008-10-13 11:59:15
1880	Zhezqazghan	KAZ	Qaraghandy	90000	2005-09-12 18:28:22
1881	Nairobi	KEN	Nairobi	2290000	2010-07-13 12:35:49
1882	Mombasa	KEN	Coast	461753	2006-04-17 17:31:23
1883	Kisumu	KEN	Nyanza	192733	2002-05-21 08:50:30
1884	Nakuru	KEN	Rift Valley	163927	2011-02-06 05:11:05
1885	Machakos	KEN	Eastern	116293	2013-03-23 07:22:27
1886	Eldoret	KEN	Rift Valley	111882	2010-01-24 12:34:40
1887	Meru	KEN	Eastern	94947	2004-07-25 18:18:15
1888	Nyeri	KEN	Central	91258	2005-11-15 07:21:59
1889	Bangui	CAF	Bangui	524000	2006-10-10 19:25:26
1890	Shanghai	CHN	Shanghai	9696300	2005-05-16 12:45:52
1891	Peking	CHN	Peking	7472000	2003-05-27 06:17:44
1892	Chongqing	CHN	Chongqing	6351600	2008-07-01 23:01:05
1893	Tianjin	CHN	Tianjin	5286800	2007-04-06 17:35:09
1894	Wuhan	CHN	Hubei	4344600	2011-09-03 12:17:18
1895	Harbin	CHN	Heilongjiang	4289800	2004-06-12 06:18:04
1896	Shenyang	CHN	Liaoning	4265200	2014-06-11 07:35:51
1897	Kanton [Guangzhou]	CHN	Guangdong	4256300	2012-05-05 12:57:06
1898	Chengdu	CHN	Sichuan	3361500	2002-10-25 11:05:58
1899	Nanking [Nanjing]	CHN	Jiangsu	2870300	2009-03-27 16:46:25
1900	Changchun	CHN	Jilin	2812000	2013-09-14 09:09:15
1901	Xi´an	CHN	Shaanxi	2761400	2012-08-15 08:16:13
1902	Dalian	CHN	Liaoning	2697000	2011-04-04 18:25:27
1903	Qingdao	CHN	Shandong	2596000	2004-05-26 08:21:56
1904	Jinan	CHN	Shandong	2278100	2009-02-10 15:01:41
1905	Hangzhou	CHN	Zhejiang	2190500	2002-01-05 07:34:25
1906	Zhengzhou	CHN	Henan	2107200	2007-03-16 17:17:31
1907	Shijiazhuang	CHN	Hebei	2041500	2004-09-23 18:04:33
1908	Taiyuan	CHN	Shanxi	1968400	2013-09-03 22:56:45
1909	Kunming	CHN	Yunnan	1829500	2003-07-14 02:16:09
1910	Changsha	CHN	Hunan	1809800	2013-02-12 16:42:07
1911	Nanchang	CHN	Jiangxi	1691600	2013-07-09 23:16:39
1912	Fuzhou	CHN	Fujian	1593800	2004-11-09 07:52:57
1913	Lanzhou	CHN	Gansu	1565800	2001-07-23 07:39:34
1914	Guiyang	CHN	Guizhou	1465200	2001-03-16 10:25:33
1915	Ningbo	CHN	Zhejiang	1371200	2007-10-01 23:54:21
1916	Hefei	CHN	Anhui	1369100	2003-06-09 13:19:32
1917	Urumti [Ürümqi]	CHN	Xinxiang	1310100	2001-04-01 10:34:33
1918	Anshan	CHN	Liaoning	1200000	2007-02-14 13:48:06
1919	Fushun	CHN	Liaoning	1200000	2007-04-18 07:16:39
1920	Nanning	CHN	Guangxi	1161800	2004-02-24 08:20:47
1921	Zibo	CHN	Shandong	1140000	2004-03-21 20:35:37
1922	Qiqihar	CHN	Heilongjiang	1070000	2002-01-17 23:43:19
1923	Jilin	CHN	Jilin	1040000	2002-08-18 07:07:41
1924	Tangshan	CHN	Hebei	1040000	2005-04-09 09:05:37
1925	Baotou	CHN	Inner Mongolia	980000	2002-11-09 13:41:35
1926	Shenzhen	CHN	Guangdong	950500	2007-12-06 09:42:07
1927	Hohhot	CHN	Inner Mongolia	916700	2012-09-26 22:47:20
1928	Handan	CHN	Hebei	840000	2002-05-19 01:58:02
1929	Wuxi	CHN	Jiangsu	830000	2012-07-03 04:22:32
1930	Xuzhou	CHN	Jiangsu	810000	2014-10-23 14:40:27
1931	Datong	CHN	Shanxi	800000	2009-08-05 04:29:16
1932	Yichun	CHN	Heilongjiang	800000	2010-05-01 06:03:41
1933	Benxi	CHN	Liaoning	770000	2005-08-02 02:29:42
1934	Luoyang	CHN	Henan	760000	2004-05-18 13:47:50
1935	Suzhou	CHN	Jiangsu	710000	2002-04-19 07:27:08
1936	Xining	CHN	Qinghai	700200	2008-11-08 18:22:15
1937	Huainan	CHN	Anhui	700000	2010-09-05 02:07:23
1938	Jixi	CHN	Heilongjiang	683885	2013-08-04 07:33:19
1939	Daqing	CHN	Heilongjiang	660000	2001-09-05 14:49:50
1940	Fuxin	CHN	Liaoning	640000	2002-02-05 11:31:56
1941	Amoy [Xiamen]	CHN	Fujian	627500	2008-12-25 05:40:35
1942	Liuzhou	CHN	Guangxi	610000	2007-09-01 08:20:01
1943	Shantou	CHN	Guangdong	580000	2004-02-28 11:15:07
1944	Jinzhou	CHN	Liaoning	570000	2007-10-15 21:56:33
1945	Mudanjiang	CHN	Heilongjiang	570000	2009-06-28 18:35:24
1946	Yinchuan	CHN	Ningxia	544500	2002-09-22 22:44:07
1947	Changzhou	CHN	Jiangsu	530000	2012-04-07 14:56:40
1948	Zhangjiakou	CHN	Hebei	530000	2008-05-19 01:33:53
1949	Dandong	CHN	Liaoning	520000	2012-02-14 10:10:20
1950	Hegang	CHN	Heilongjiang	520000	2014-01-15 10:46:55
1951	Kaifeng	CHN	Henan	510000	2013-12-09 16:57:29
1952	Jiamusi	CHN	Heilongjiang	493409	2007-10-22 10:19:09
1953	Liaoyang	CHN	Liaoning	492559	2001-09-09 20:54:45
1954	Hengyang	CHN	Hunan	487148	2008-03-20 02:21:01
1955	Baoding	CHN	Hebei	483155	2013-05-23 17:29:01
1956	Hunjiang	CHN	Jilin	482043	2013-04-21 13:32:56
1957	Xinxiang	CHN	Henan	473762	2014-02-12 09:10:19
1958	Huangshi	CHN	Hubei	457601	2014-08-05 17:15:32
1959	Haikou	CHN	Hainan	454300	2006-01-18 10:43:21
1960	Yantai	CHN	Shandong	452127	2013-04-13 10:02:08
1961	Bengbu	CHN	Anhui	449245	2003-06-25 04:53:07
1962	Xiangtan	CHN	Hunan	441968	2001-02-25 21:56:08
1963	Weifang	CHN	Shandong	428522	2010-07-19 06:50:02
1964	Wuhu	CHN	Anhui	425740	2007-08-15 04:32:14
1965	Pingxiang	CHN	Jiangxi	425579	2011-09-04 08:28:36
1966	Yingkou	CHN	Liaoning	421589	2011-01-13 18:59:48
1967	Anyang	CHN	Henan	420332	2012-08-18 06:50:05
1968	Panzhihua	CHN	Sichuan	415466	2014-03-10 18:55:23
1969	Pingdingshan	CHN	Henan	410775	2014-12-27 13:09:41
1970	Xiangfan	CHN	Hubei	410407	2006-07-07 07:48:20
1971	Zhuzhou	CHN	Hunan	409924	2003-12-10 13:26:42
1972	Jiaozuo	CHN	Henan	409100	2010-08-21 10:34:29
1973	Wenzhou	CHN	Zhejiang	401871	2001-09-21 04:47:44
1974	Zhangjiang	CHN	Guangdong	400997	2002-01-04 05:56:04
1975	Zigong	CHN	Sichuan	393184	2006-10-06 12:23:22
1976	Shuangyashan	CHN	Heilongjiang	386081	2001-09-05 18:20:48
1977	Zaozhuang	CHN	Shandong	380846	2006-04-16 01:26:03
1978	Yakeshi	CHN	Inner Mongolia	377869	2001-04-24 05:25:20
1979	Yichang	CHN	Hubei	371601	2009-10-14 22:51:26
1980	Zhenjiang	CHN	Jiangsu	368316	2002-05-11 04:10:41
1981	Huaibei	CHN	Anhui	366549	2014-02-07 07:43:40
1982	Qinhuangdao	CHN	Hebei	364972	2006-05-12 11:20:46
1983	Guilin	CHN	Guangxi	364130	2012-02-10 06:36:43
1984	Liupanshui	CHN	Guizhou	363954	2003-01-02 10:32:42
1985	Panjin	CHN	Liaoning	362773	2006-06-01 02:09:42
1986	Yangquan	CHN	Shanxi	362268	2013-06-22 17:37:44
1987	Jinxi	CHN	Liaoning	357052	2011-08-14 23:14:33
1988	Liaoyuan	CHN	Jilin	354141	2009-03-09 01:48:11
1989	Lianyungang	CHN	Jiangsu	354139	2010-03-26 05:36:30
1990	Xianyang	CHN	Shaanxi	352125	2002-07-09 13:58:39
1991	Tai´an	CHN	Shandong	350696	2005-02-12 05:11:56
1992	Chifeng	CHN	Inner Mongolia	350077	2002-09-03 10:34:44
1993	Shaoguan	CHN	Guangdong	350043	2013-02-24 12:11:07
1994	Nantong	CHN	Jiangsu	343341	2003-05-27 03:06:19
1995	Leshan	CHN	Sichuan	341128	2014-01-20 09:05:30
1996	Baoji	CHN	Shaanxi	337765	2013-07-12 09:33:32
1997	Linyi	CHN	Shandong	324720	2004-01-02 08:33:30
1998	Tonghua	CHN	Jilin	324600	2010-07-04 10:03:35
1999	Siping	CHN	Jilin	317223	2006-01-18 22:05:34
2000	Changzhi	CHN	Shanxi	317144	2001-06-13 23:04:44
2001	Tengzhou	CHN	Shandong	315083	2012-08-24 20:12:05
2002	Chaozhou	CHN	Guangdong	313469	2011-02-02 12:57:06
2003	Yangzhou	CHN	Jiangsu	312892	2008-06-19 14:38:53
2004	Dongwan	CHN	Guangdong	308669	2006-08-06 09:11:52
2005	Ma´anshan	CHN	Anhui	305421	2006-04-14 03:33:24
2006	Foshan	CHN	Guangdong	303160	2001-12-17 03:21:58
2007	Yueyang	CHN	Hunan	302800	2006-12-05 21:20:55
2008	Xingtai	CHN	Hebei	302789	2006-01-18 07:17:31
2009	Changde	CHN	Hunan	301276	2006-03-10 12:23:31
2010	Shihezi	CHN	Xinxiang	299676	2002-08-03 03:58:29
2011	Yancheng	CHN	Jiangsu	296831	2007-04-10 13:28:57
2012	Jiujiang	CHN	Jiangxi	291187	2002-03-20 22:41:41
2013	Dongying	CHN	Shandong	281728	2005-01-23 06:21:10
2014	Shashi	CHN	Hubei	281352	2004-08-20 02:23:26
2015	Xintai	CHN	Shandong	281248	2007-06-12 01:15:37
2016	Jingdezhen	CHN	Jiangxi	281183	2012-07-05 10:04:52
2017	Tongchuan	CHN	Shaanxi	280657	2005-09-07 06:21:14
2018	Zhongshan	CHN	Guangdong	278829	2003-07-15 20:03:11
2019	Shiyan	CHN	Hubei	273786	2012-08-27 04:10:22
2020	Tieli	CHN	Heilongjiang	265683	2001-03-28 01:15:10
2021	Jining	CHN	Shandong	265248	2014-05-21 09:37:08
2022	Wuhai	CHN	Inner Mongolia	264081	2012-06-20 06:12:15
2023	Mianyang	CHN	Sichuan	262947	2008-12-20 18:40:07
2024	Luzhou	CHN	Sichuan	262892	2003-07-20 21:51:37
2025	Zunyi	CHN	Guizhou	261862	2012-08-09 23:49:11
2026	Shizuishan	CHN	Ningxia	257862	2011-12-19 08:39:16
2027	Neijiang	CHN	Sichuan	256012	2002-02-01 20:44:28
2028	Tongliao	CHN	Inner Mongolia	255129	2005-02-24 15:15:44
2029	Tieling	CHN	Liaoning	254842	2014-06-25 05:28:54
2030	Wafangdian	CHN	Liaoning	251733	2002-02-02 17:52:03
2031	Anqing	CHN	Anhui	250718	2006-03-08 14:11:02
2032	Shaoyang	CHN	Hunan	247227	2006-07-12 05:40:04
2033	Laiwu	CHN	Shandong	246833	2012-07-24 05:06:57
2034	Chengde	CHN	Hebei	246799	2012-08-27 04:47:25
2035	Tianshui	CHN	Gansu	244974	2002-10-16 12:15:58
2036	Nanyang	CHN	Henan	243303	2009-07-13 12:10:56
2037	Cangzhou	CHN	Hebei	242708	2007-07-21 03:15:46
2038	Yibin	CHN	Sichuan	241019	2001-01-15 07:57:43
2039	Huaiyin	CHN	Jiangsu	239675	2012-09-27 16:53:33
2040	Dunhua	CHN	Jilin	235100	2009-01-10 17:44:42
2041	Yanji	CHN	Jilin	230892	2005-11-22 22:01:56
2042	Jiangmen	CHN	Guangdong	230587	2002-08-03 03:52:43
2043	Tongling	CHN	Anhui	228017	2004-06-12 09:16:34
2044	Suihua	CHN	Heilongjiang	227881	2009-11-12 03:06:32
2045	Gongziling	CHN	Jilin	226569	2013-05-17 13:14:56
2046	Xiantao	CHN	Hubei	222884	2004-11-16 07:43:22
2047	Chaoyang	CHN	Liaoning	222394	2007-10-24 23:47:58
2048	Ganzhou	CHN	Jiangxi	220129	2010-11-06 08:32:42
2049	Huzhou	CHN	Zhejiang	218071	2007-05-21 05:43:45
2050	Baicheng	CHN	Jilin	217987	2010-02-16 04:33:16
2051	Shangzi	CHN	Heilongjiang	215373	2003-03-20 14:45:55
2052	Yangjiang	CHN	Guangdong	215196	2002-08-22 21:23:32
2053	Qitaihe	CHN	Heilongjiang	214957	2005-04-25 23:59:56
2054	Gejiu	CHN	Yunnan	214294	2001-12-09 07:09:08
2055	Jiangyin	CHN	Jiangsu	213659	2009-04-10 12:27:05
2056	Hebi	CHN	Henan	212976	2009-03-12 20:12:03
2057	Jiaxing	CHN	Zhejiang	211526	2006-03-23 08:38:22
2058	Wuzhou	CHN	Guangxi	210452	2004-07-02 20:45:02
2059	Meihekou	CHN	Jilin	209038	2001-09-15 11:30:02
2060	Xuchang	CHN	Henan	208815	2002-06-22 19:10:44
2061	Liaocheng	CHN	Shandong	207844	2011-11-28 04:25:14
2062	Haicheng	CHN	Liaoning	205560	2002-05-07 07:14:06
2063	Qianjiang	CHN	Hubei	205504	2008-04-14 23:14:20
2064	Baiyin	CHN	Gansu	204970	2013-05-07 18:48:53
2065	Bei´an	CHN	Heilongjiang	204899	2004-01-16 21:22:02
2066	Yixing	CHN	Jiangsu	200824	2009-06-07 16:48:50
2067	Laizhou	CHN	Shandong	198664	2008-08-13 23:08:33
2068	Qaramay	CHN	Xinxiang	197602	2003-01-07 19:19:04
2069	Acheng	CHN	Heilongjiang	197595	2009-07-12 06:22:48
2070	Dezhou	CHN	Shandong	195485	2009-12-18 23:43:25
2071	Nanping	CHN	Fujian	195064	2008-03-02 05:58:59
2072	Zhaoqing	CHN	Guangdong	194784	2010-04-08 17:27:12
2073	Beipiao	CHN	Liaoning	194301	2001-01-21 01:37:48
2074	Fengcheng	CHN	Jiangxi	193784	2010-12-08 08:06:55
2075	Fuyu	CHN	Jilin	192981	2012-11-07 07:53:31
2076	Xinyang	CHN	Henan	192509	2006-02-20 15:42:03
2077	Dongtai	CHN	Jiangsu	192247	2002-07-13 19:31:30
2078	Yuci	CHN	Shanxi	191356	2002-09-25 12:35:21
2079	Honghu	CHN	Hubei	190772	2011-02-15 19:49:18
2080	Ezhou	CHN	Hubei	190123	2014-10-07 14:34:43
2081	Heze	CHN	Shandong	189293	2007-03-01 10:10:08
2082	Daxian	CHN	Sichuan	188101	2014-02-21 12:48:14
2083	Linfen	CHN	Shanxi	187309	2010-04-04 15:45:43
2084	Tianmen	CHN	Hubei	186332	2009-07-17 09:29:48
2085	Yiyang	CHN	Hunan	185818	2010-07-15 23:51:28
2086	Quanzhou	CHN	Fujian	185154	2005-01-12 05:30:59
2087	Rizhao	CHN	Shandong	185048	2006-08-28 03:16:48
2088	Deyang	CHN	Sichuan	182488	2009-10-08 23:04:36
2089	Guangyuan	CHN	Sichuan	182241	2006-10-25 01:33:34
2090	Changshu	CHN	Jiangsu	181805	2004-03-16 19:57:39
2091	Zhangzhou	CHN	Fujian	181424	2006-08-18 04:52:03
2092	Hailar	CHN	Inner Mongolia	180650	2009-09-24 18:38:14
2093	Nanchong	CHN	Sichuan	180273	2006-01-27 12:03:20
2094	Jiutai	CHN	Jilin	180130	2011-02-12 13:13:21
2095	Zhaodong	CHN	Heilongjiang	179976	2011-05-03 23:41:52
2096	Shaoxing	CHN	Zhejiang	179818	2014-10-16 23:51:57
2097	Fuyang	CHN	Anhui	179572	2011-10-25 05:44:12
2098	Maoming	CHN	Guangdong	178683	2014-12-04 14:33:13
2099	Qujing	CHN	Yunnan	178669	2014-05-14 13:51:24
2100	Ghulja	CHN	Xinxiang	177193	2013-10-22 12:18:21
2101	Jiaohe	CHN	Jilin	176367	2009-12-27 03:18:47
2102	Puyang	CHN	Henan	175988	2002-06-14 22:01:17
2103	Huadian	CHN	Jilin	175873	2008-07-22 02:25:31
2104	Jiangyou	CHN	Sichuan	175753	2012-12-20 12:09:12
2105	Qashqar	CHN	Xinxiang	174570	2011-04-27 13:36:19
2106	Anshun	CHN	Guizhou	174142	2009-10-02 01:11:52
2107	Fuling	CHN	Sichuan	173878	2011-01-27 12:35:15
2108	Xinyu	CHN	Jiangxi	173524	2003-11-17 11:04:53
2109	Hanzhong	CHN	Shaanxi	169930	2005-07-28 14:54:47
2110	Danyang	CHN	Jiangsu	169603	2012-01-06 19:23:14
2111	Chenzhou	CHN	Hunan	169400	2003-01-14 12:11:40
2112	Xiaogan	CHN	Hubei	166280	2012-01-22 19:18:31
2113	Shangqiu	CHN	Henan	164880	2012-12-25 12:01:25
2114	Zhuhai	CHN	Guangdong	164747	2012-01-05 14:13:28
2115	Qingyuan	CHN	Guangdong	164641	2009-09-15 08:41:47
2116	Aqsu	CHN	Xinxiang	164092	2011-08-27 10:23:43
2117	Jining	CHN	Inner Mongolia	163552	2010-02-01 15:34:18
2118	Xiaoshan	CHN	Zhejiang	162930	2001-07-20 16:15:07
2119	Zaoyang	CHN	Hubei	162198	2003-03-17 14:38:43
2120	Xinghua	CHN	Jiangsu	161910	2007-08-22 12:31:40
2121	Hami	CHN	Xinxiang	161315	2009-05-16 21:42:29
2122	Huizhou	CHN	Guangdong	161023	2009-12-23 23:01:23
2123	Jinmen	CHN	Hubei	160794	2007-06-15 19:04:24
2124	Sanming	CHN	Fujian	160691	2014-04-18 04:20:15
2125	Ulanhot	CHN	Inner Mongolia	159538	2010-06-06 08:32:15
2126	Korla	CHN	Xinxiang	159344	2011-03-10 23:33:52
2127	Wanxian	CHN	Sichuan	156823	2003-10-26 02:18:43
2128	Rui´an	CHN	Zhejiang	156468	2012-12-19 14:32:10
2129	Zhoushan	CHN	Zhejiang	156317	2001-06-28 06:45:25
2130	Liangcheng	CHN	Shandong	156307	2013-05-05 14:40:23
2131	Jiaozhou	CHN	Shandong	153364	2008-05-28 23:22:12
2132	Taizhou	CHN	Jiangsu	152442	2011-10-14 18:18:46
2133	Suzhou	CHN	Anhui	151862	2014-10-02 12:49:59
2134	Yichun	CHN	Jiangxi	151585	2003-06-05 16:13:21
2135	Taonan	CHN	Jilin	150168	2002-02-24 06:12:46
2136	Pingdu	CHN	Shandong	150123	2010-06-17 23:27:23
2137	Ji´an	CHN	Jiangxi	148583	2014-11-04 01:57:30
2138	Longkou	CHN	Shandong	148362	2009-09-04 19:41:05
2139	Langfang	CHN	Hebei	148105	2001-12-21 11:50:13
2140	Zhoukou	CHN	Henan	146288	2006-07-02 22:52:49
2141	Suining	CHN	Sichuan	146086	2002-01-09 17:44:01
2142	Yulin	CHN	Guangxi	144467	2009-11-21 12:33:50
2143	Jinhua	CHN	Zhejiang	144280	2005-06-09 06:04:28
2144	Liu´an	CHN	Anhui	144248	2007-05-25 14:26:12
2145	Shuangcheng	CHN	Heilongjiang	142659	2013-02-09 11:32:17
2146	Suizhou	CHN	Hubei	142302	2011-04-26 02:27:41
2147	Ankang	CHN	Shaanxi	142170	2014-07-03 11:43:06
2148	Weinan	CHN	Shaanxi	140169	2012-10-11 12:43:39
2149	Longjing	CHN	Jilin	139417	2007-10-15 12:21:11
2150	Da´an	CHN	Jilin	138963	2008-06-15 04:06:54
2151	Lengshuijiang	CHN	Hunan	137994	2013-05-17 18:56:45
2152	Laiyang	CHN	Shandong	137080	2004-11-06 08:36:25
2153	Xianning	CHN	Hubei	136811	2014-05-06 04:07:52
2154	Dali	CHN	Yunnan	136554	2008-07-16 18:32:13
2155	Anda	CHN	Heilongjiang	136446	2012-02-27 22:36:24
2156	Jincheng	CHN	Shanxi	136396	2004-03-10 18:33:43
2157	Longyan	CHN	Fujian	134481	2014-03-12 08:04:07
2158	Xichang	CHN	Sichuan	134419	2011-05-26 10:34:58
2159	Wendeng	CHN	Shandong	133910	2004-08-09 01:26:55
2160	Hailun	CHN	Heilongjiang	133565	2009-10-17 07:23:46
2161	Binzhou	CHN	Shandong	133555	2012-01-17 14:33:04
2162	Linhe	CHN	Inner Mongolia	133183	2010-05-15 17:31:48
2163	Wuwei	CHN	Gansu	133101	2007-09-04 02:33:43
2164	Duyun	CHN	Guizhou	132971	2004-06-11 16:25:18
2165	Mishan	CHN	Heilongjiang	132744	2014-11-08 16:54:26
2166	Shangrao	CHN	Jiangxi	132455	2010-02-19 23:02:40
2167	Changji	CHN	Xinxiang	132260	2009-10-08 03:59:14
2168	Meixian	CHN	Guangdong	132156	2009-06-28 23:59:24
2169	Yushu	CHN	Jilin	131861	2012-11-14 13:22:35
2170	Tiefa	CHN	Liaoning	131807	2010-09-09 05:37:06
2171	Huai´an	CHN	Jiangsu	131149	2010-10-08 01:39:18
2172	Leiyang	CHN	Hunan	130115	2014-06-09 02:22:19
2173	Zalantun	CHN	Inner Mongolia	130031	2002-11-15 14:47:39
2174	Weihai	CHN	Shandong	128888	2012-05-23 21:41:01
2175	Loudi	CHN	Hunan	128418	2013-02-14 10:17:39
2176	Qingzhou	CHN	Shandong	128258	2004-07-13 22:30:28
2177	Qidong	CHN	Jiangsu	126872	2011-11-16 05:17:04
2178	Huaihua	CHN	Hunan	126785	2013-11-20 11:17:24
2179	Luohe	CHN	Henan	126438	2007-04-12 02:07:39
2180	Chuzhou	CHN	Anhui	125341	2014-09-02 07:42:32
2181	Kaiyuan	CHN	Liaoning	124219	2014-03-03 02:56:50
2182	Linqing	CHN	Shandong	123958	2002-09-23 23:18:08
2183	Chaohu	CHN	Anhui	123676	2012-07-22 20:20:30
2184	Laohekou	CHN	Hubei	123366	2005-02-08 19:25:23
2185	Dujiangyan	CHN	Sichuan	123357	2011-04-15 15:52:02
2186	Zhumadian	CHN	Henan	123232	2001-11-03 04:07:52
2187	Linchuan	CHN	Jiangxi	121949	2009-04-21 06:55:55
2188	Jiaonan	CHN	Shandong	121397	2006-08-24 17:34:20
2189	Sanmenxia	CHN	Henan	120523	2007-07-13 22:27:56
2190	Heyuan	CHN	Guangdong	120101	2004-10-17 12:43:16
2191	Manzhouli	CHN	Inner Mongolia	120023	2001-10-01 23:03:13
2192	Lhasa	CHN	Tibet	120000	2010-07-02 22:33:45
2193	Lianyuan	CHN	Hunan	118858	2014-10-24 21:54:16
2194	Kuytun	CHN	Xinxiang	118553	2008-11-10 09:56:31
2195	Puqi	CHN	Hubei	117264	2004-08-04 18:10:33
2196	Hongjiang	CHN	Hunan	116188	2010-05-17 03:04:33
2197	Qinzhou	CHN	Guangxi	114586	2005-10-02 18:30:28
2198	Renqiu	CHN	Hebei	114256	2007-06-10 12:12:23
2199	Yuyao	CHN	Zhejiang	114065	2007-07-02 13:49:12
2200	Guigang	CHN	Guangxi	114025	2006-05-02 04:34:19
2201	Kaili	CHN	Guizhou	113958	2009-11-04 01:15:29
2202	Yan´an	CHN	Shaanxi	113277	2008-07-11 23:08:25
2203	Beihai	CHN	Guangxi	112673	2009-09-10 16:17:16
2204	Xuangzhou	CHN	Anhui	112673	2009-09-01 20:29:41
2205	Quzhou	CHN	Zhejiang	112373	2012-10-11 09:33:53
2206	Yong´an	CHN	Fujian	111762	2011-07-11 15:11:46
2207	Zixing	CHN	Hunan	110048	2009-11-24 21:41:47
2208	Liyang	CHN	Jiangsu	109520	2010-02-24 03:26:18
2209	Yizheng	CHN	Jiangsu	109268	2004-05-10 09:23:01
2210	Yumen	CHN	Gansu	109234	2010-10-22 09:14:21
2211	Liling	CHN	Hunan	108504	2014-05-06 21:48:07
2212	Yuncheng	CHN	Shanxi	108359	2006-05-11 17:50:41
2213	Shanwei	CHN	Guangdong	107847	2006-07-13 23:37:31
2214	Cixi	CHN	Zhejiang	107329	2005-04-17 20:31:56
2215	Yuanjiang	CHN	Hunan	107004	2012-09-20 20:05:40
2216	Bozhou	CHN	Anhui	106346	2004-11-04 17:56:33
2217	Jinchang	CHN	Gansu	105287	2001-10-08 14:45:37
2218	Fu´an	CHN	Fujian	105265	2013-01-12 09:27:34
2219	Suqian	CHN	Jiangsu	105021	2012-04-03 04:03:50
2220	Shishou	CHN	Hubei	104571	2008-01-22 22:30:19
2221	Hengshui	CHN	Hebei	104269	2009-02-27 11:12:45
2222	Danjiangkou	CHN	Hubei	103211	2014-07-21 13:37:43
2223	Fujin	CHN	Heilongjiang	103104	2008-03-20 15:39:47
2224	Sanya	CHN	Hainan	102820	2002-12-07 12:03:31
2225	Guangshui	CHN	Hubei	102770	2012-06-10 05:14:35
2226	Huangshan	CHN	Anhui	102628	2001-09-01 23:16:43
2227	Xingcheng	CHN	Liaoning	102384	2011-07-13 13:28:40
2228	Zhucheng	CHN	Shandong	102134	2013-11-01 10:57:21
2229	Kunshan	CHN	Jiangsu	102052	2004-01-16 17:02:46
2230	Haining	CHN	Zhejiang	100478	2011-05-15 07:51:11
2231	Pingliang	CHN	Gansu	99265	2004-08-17 13:03:24
2232	Fuqing	CHN	Fujian	99193	2008-09-16 11:23:06
2233	Xinzhou	CHN	Shanxi	98667	2002-11-21 09:09:31
2234	Jieyang	CHN	Guangdong	98531	2008-08-10 08:06:03
2235	Zhangjiagang	CHN	Jiangsu	97994	2005-03-10 19:47:44
2236	Tong Xian	CHN	Peking	97168	2005-07-14 03:38:05
2237	Ya´an	CHN	Sichuan	95900	2008-01-17 15:03:23
2238	Jinzhou	CHN	Liaoning	95761	2004-11-09 18:04:13
2239	Emeishan	CHN	Sichuan	94000	2011-02-17 06:05:30
2240	Enshi	CHN	Hubei	93056	2005-02-26 20:34:59
2241	Bose	CHN	Guangxi	93009	2005-11-26 08:23:24
2242	Yuzhou	CHN	Henan	92889	2014-10-13 15:14:52
2243	Kaiyuan	CHN	Yunnan	91999	2001-01-25 14:18:18
2244	Tumen	CHN	Jilin	91471	2013-04-05 17:50:38
2245	Putian	CHN	Fujian	91030	2010-01-23 19:36:37
2246	Linhai	CHN	Zhejiang	90870	2008-05-15 23:56:56
2247	Xilin Hot	CHN	Inner Mongolia	90646	2007-10-12 21:23:43
2248	Shaowu	CHN	Fujian	90286	2014-12-12 04:23:46
2249	Junan	CHN	Shandong	90222	2002-11-02 19:49:31
2250	Huaying	CHN	Sichuan	89400	2004-01-23 01:35:09
2251	Pingyi	CHN	Shandong	89373	2005-09-26 04:51:20
2252	Huangyan	CHN	Zhejiang	89288	2012-09-26 10:15:53
2253	Bishkek	KGZ	Bishkek shaary	589400	2001-10-26 23:21:14
2254	Osh	KGZ	Osh	222700	2007-09-25 03:20:03
2255	Bikenibeu	KIR	South Tarawa	5055	2008-01-04 01:05:19
2256	Bairiki	KIR	South Tarawa	2226	2007-04-28 08:32:57
2257	Santafé de Bogotá	COL	Santafé de Bogotá	6260862	2005-07-06 01:54:07
2258	Cali	COL	Valle	2077386	2011-05-20 20:13:13
2259	Medellín	COL	Antioquia	1861265	2004-03-11 14:18:02
2260	Barranquilla	COL	Atlántico	1223260	2004-05-25 12:29:53
2261	Cartagena	COL	Bolívar	805757	2002-01-23 20:37:15
2262	Cúcuta	COL	Norte de Santander	606932	2002-11-05 18:20:13
2263	Bucaramanga	COL	Santander	515555	2012-10-13 09:26:27
2264	Ibagué	COL	Tolima	393664	2007-04-01 12:50:53
2265	Pereira	COL	Risaralda	381725	2004-03-07 08:07:42
2266	Santa Marta	COL	Magdalena	359147	2014-01-09 22:35:44
2267	Manizales	COL	Caldas	337580	2005-11-27 07:22:01
2268	Bello	COL	Antioquia	333470	2007-02-02 18:50:34
2269	Pasto	COL	Nariño	332396	2004-08-11 12:34:04
2270	Neiva	COL	Huila	300052	2001-02-15 14:22:53
2271	Soledad	COL	Atlántico	295058	2002-10-05 16:46:45
2272	Armenia	COL	Quindío	288977	2013-06-25 01:01:57
2273	Villavicencio	COL	Meta	273140	2012-10-09 19:39:56
2274	Soacha	COL	Cundinamarca	272058	2005-11-10 02:09:42
2275	Valledupar	COL	Cesar	263247	2003-07-08 16:56:25
2276	Montería	COL	Córdoba	248245	2002-04-28 06:15:35
2277	Itagüí	COL	Antioquia	228985	2006-11-26 23:14:19
2278	Palmira	COL	Valle	226509	2004-11-13 07:04:25
2279	Buenaventura	COL	Valle	224336	2005-02-02 02:50:05
2280	Floridablanca	COL	Santander	221913	2007-12-06 19:17:21
2281	Sincelejo	COL	Sucre	220704	2003-01-07 23:21:52
2282	Popayán	COL	Cauca	200719	2012-11-12 02:42:04
2283	Barrancabermeja	COL	Santander	178020	2010-08-10 23:01:19
2284	Dos Quebradas	COL	Risaralda	159363	2009-11-24 13:28:02
2285	Tuluá	COL	Valle	152488	2014-07-02 09:14:28
2286	Envigado	COL	Antioquia	135848	2006-03-09 01:22:30
2287	Cartago	COL	Valle	125884	2010-06-19 05:29:53
2288	Girardot	COL	Cundinamarca	110963	2005-04-15 12:55:38
2289	Buga	COL	Valle	110699	2005-04-09 21:02:12
2290	Tunja	COL	Boyacá	109740	2011-10-04 04:30:33
2291	Florencia	COL	Caquetá	108574	2010-01-09 12:42:24
2292	Maicao	COL	La Guajira	108053	2012-08-05 18:02:22
2293	Sogamoso	COL	Boyacá	107728	2006-07-24 19:42:18
2294	Giron	COL	Santander	90688	2009-01-25 23:38:44
2295	Moroni	COM	Njazidja	36000	2004-05-05 10:25:31
2296	Brazzaville	COG	Brazzaville	950000	2007-07-11 22:41:44
2297	Pointe-Noire	COG	Kouilou	500000	2004-10-24 07:52:05
2298	Kinshasa	COD	Kinshasa	5064000	2003-11-19 23:50:56
2299	Lubumbashi	COD	Shaba	851381	2002-12-22 11:21:10
2300	Mbuji-Mayi	COD	East Kasai	806475	2012-12-09 23:33:08
2301	Kolwezi	COD	Shaba	417810	2010-12-11 20:56:10
2302	Kisangani	COD	Haute-Zaïre	417517	2004-05-13 19:26:44
2303	Kananga	COD	West Kasai	393030	2010-01-10 12:37:29
2304	Likasi	COD	Shaba	299118	2006-08-13 04:03:05
2305	Bukavu	COD	South Kivu	201569	2002-10-09 22:23:27
2306	Kikwit	COD	Bandundu	182142	2002-02-19 03:34:23
2307	Tshikapa	COD	West Kasai	180860	2012-01-01 02:23:37
2308	Matadi	COD	Bas-Zaïre	172730	2014-10-21 05:37:07
2309	Mbandaka	COD	Equateur	169841	2012-12-02 13:16:31
2310	Mwene-Ditu	COD	East Kasai	137459	2013-03-11 09:31:48
2311	Boma	COD	Bas-Zaïre	135284	2012-07-01 11:09:16
2312	Uvira	COD	South Kivu	115590	2011-04-11 02:55:28
2313	Butembo	COD	North Kivu	109406	2011-08-23 01:52:25
2314	Goma	COD	North Kivu	109094	2014-09-17 21:12:19
2315	Kalemie	COD	Shaba	101309	2002-08-13 18:53:54
2316	Bantam	CCK	Home Island	503	2011-10-02 23:38:46
2317	West Island	CCK	West Island	167	2011-02-14 11:43:30
2318	Pyongyang	PRK	Pyongyang-si	2484000	2002-08-17 23:19:54
2319	Hamhung	PRK	Hamgyong N	709730	2006-01-24 23:52:44
2320	Chongjin	PRK	Hamgyong P	582480	2004-12-16 11:56:23
2321	Nampo	PRK	Nampo-si	566200	2005-03-01 08:48:28
2322	Sinuiju	PRK	Pyongan P	326011	2010-07-05 04:58:58
2323	Wonsan	PRK	Kangwon	300148	2002-09-07 16:55:21
2324	Phyongsong	PRK	Pyongan N	272934	2003-12-08 19:23:53
2325	Sariwon	PRK	Hwanghae P	254146	2001-10-21 08:31:09
2326	Haeju	PRK	Hwanghae N	229172	2005-05-21 11:06:16
2327	Kanggye	PRK	Chagang	223410	2013-10-08 13:23:46
2328	Kimchaek	PRK	Hamgyong P	179000	2014-12-25 14:16:03
2329	Hyesan	PRK	Yanggang	178020	2010-08-21 03:20:36
2330	Kaesong	PRK	Kaesong-si	171500	2008-07-26 03:44:27
2331	Seoul	KOR	Seoul	9981619	2008-07-24 12:52:33
2332	Pusan	KOR	Pusan	3804522	2010-09-07 14:22:02
2333	Inchon	KOR	Inchon	2559424	2004-07-16 03:35:22
2334	Taegu	KOR	Taegu	2548568	2013-06-16 04:48:12
2335	Taejon	KOR	Taejon	1425835	2007-04-28 02:09:59
2336	Kwangju	KOR	Kwangju	1368341	2010-03-08 12:05:43
2337	Ulsan	KOR	Kyongsangnam	1084891	2014-03-02 09:27:16
2338	Songnam	KOR	Kyonggi	869094	2006-11-27 20:26:36
2339	Puchon	KOR	Kyonggi	779412	2012-03-25 17:43:53
2340	Suwon	KOR	Kyonggi	755550	2011-05-18 01:20:35
2341	Anyang	KOR	Kyonggi	591106	2006-09-28 17:07:29
2342	Chonju	KOR	Chollabuk	563153	2014-01-12 21:04:53
2343	Chongju	KOR	Chungchongbuk	531376	2007-07-01 21:40:10
2344	Koyang	KOR	Kyonggi	518282	2011-08-12 13:58:58
2345	Ansan	KOR	Kyonggi	510314	2003-09-24 09:09:36
2346	Pohang	KOR	Kyongsangbuk	508899	2010-03-26 13:34:33
2347	Chang-won	KOR	Kyongsangnam	481694	2001-02-17 10:40:34
2348	Masan	KOR	Kyongsangnam	441242	2011-12-22 23:59:57
2349	Kwangmyong	KOR	Kyonggi	350914	2008-07-22 23:04:21
2350	Chonan	KOR	Chungchongnam	330259	2005-08-05 13:01:09
2351	Chinju	KOR	Kyongsangnam	329886	2009-10-08 23:13:45
2352	Iksan	KOR	Chollabuk	322685	2001-09-07 23:21:37
2353	Pyongtaek	KOR	Kyonggi	312927	2013-04-20 07:40:38
2354	Kumi	KOR	Kyongsangbuk	311431	2001-08-17 15:03:12
2355	Uijongbu	KOR	Kyonggi	276111	2009-02-22 11:29:01
2356	Kyongju	KOR	Kyongsangbuk	272968	2006-08-23 17:30:04
2357	Kunsan	KOR	Chollabuk	266569	2012-10-16 11:08:33
2358	Cheju	KOR	Cheju	258511	2002-12-19 07:23:37
2359	Kimhae	KOR	Kyongsangnam	256370	2009-04-28 22:36:18
2360	Sunchon	KOR	Chollanam	249263	2002-09-26 23:38:11
2361	Mokpo	KOR	Chollanam	247452	2012-09-18 05:15:56
2362	Yong-in	KOR	Kyonggi	242643	2013-07-24 20:02:55
2363	Wonju	KOR	Kang-won	237460	2001-08-07 06:20:39
2364	Kunpo	KOR	Kyonggi	235233	2012-06-28 13:13:57
2365	Chunchon	KOR	Kang-won	234528	2014-03-09 03:58:41
2366	Namyangju	KOR	Kyonggi	229060	2004-05-19 20:47:16
2367	Kangnung	KOR	Kang-won	220403	2011-06-20 17:51:45
2368	Chungju	KOR	Chungchongbuk	205206	2014-01-18 17:50:56
2369	Andong	KOR	Kyongsangbuk	188443	2004-09-28 11:05:12
2370	Yosu	KOR	Chollanam	183596	2002-08-14 09:05:45
2371	Kyongsan	KOR	Kyongsangbuk	173746	2009-10-02 05:40:31
2372	Paju	KOR	Kyonggi	163379	2002-10-12 21:03:07
2373	Yangsan	KOR	Kyongsangnam	163351	2006-11-23 01:35:45
2374	Ichon	KOR	Kyonggi	155332	2006-07-19 20:56:34
2375	Asan	KOR	Chungchongnam	154663	2002-09-17 09:54:26
2376	Koje	KOR	Kyongsangnam	147562	2003-11-24 13:34:39
2377	Kimchon	KOR	Kyongsangbuk	147027	2010-08-12 23:08:55
2378	Nonsan	KOR	Chungchongnam	146619	2007-11-15 07:43:24
2379	Kuri	KOR	Kyonggi	142173	2014-09-02 17:55:47
2380	Chong-up	KOR	Chollabuk	139111	2003-10-05 01:54:37
2381	Chechon	KOR	Chungchongbuk	137070	2008-02-05 19:36:11
2382	Sosan	KOR	Chungchongnam	134746	2003-02-15 10:44:42
2383	Shihung	KOR	Kyonggi	133443	2014-03-02 07:35:12
2384	Tong-yong	KOR	Kyongsangnam	131717	2007-12-05 19:55:46
2385	Kongju	KOR	Chungchongnam	131229	2006-04-20 13:11:23
2386	Yongju	KOR	Kyongsangbuk	131097	2012-09-11 01:26:51
2387	Chinhae	KOR	Kyongsangnam	125997	2003-05-10 20:54:54
2388	Sangju	KOR	Kyongsangbuk	124116	2006-02-01 05:44:53
2389	Poryong	KOR	Chungchongnam	122604	2008-05-23 05:25:43
2390	Kwang-yang	KOR	Chollanam	122052	2013-08-27 17:57:17
2391	Miryang	KOR	Kyongsangnam	121501	2012-01-02 19:38:53
2392	Hanam	KOR	Kyonggi	115812	2002-04-05 09:02:59
2393	Kimje	KOR	Chollabuk	115427	2013-02-11 06:49:11
2394	Yongchon	KOR	Kyongsangbuk	113511	2013-09-19 13:27:52
2395	Sachon	KOR	Kyongsangnam	113494	2005-02-03 21:53:20
2396	Uiwang	KOR	Kyonggi	108788	2014-01-21 06:36:03
2397	Naju	KOR	Chollanam	107831	2012-01-21 22:33:17
2398	Namwon	KOR	Chollabuk	103544	2009-04-14 16:23:17
2399	Tonghae	KOR	Kang-won	95472	2007-06-11 20:55:09
2400	Mun-gyong	KOR	Kyongsangbuk	92239	2005-06-11 15:10:17
2401	Athenai	GRC	Attika	772072	2013-05-03 23:18:44
2402	Thessaloniki	GRC	Central Macedonia	383967	2002-04-16 20:51:53
2403	Pireus	GRC	Attika	182671	2009-04-09 13:42:54
2404	Patras	GRC	West Greece	153344	2001-08-08 08:20:39
2405	Peristerion	GRC	Attika	137288	2014-08-26 11:30:33
2406	Herakleion	GRC	Crete	116178	2009-09-18 02:41:34
2407	Kallithea	GRC	Attika	114233	2008-02-12 23:40:19
2408	Larisa	GRC	Thessalia	113090	2002-11-23 04:09:34
2409	Zagreb	HRV	Grad Zagreb	706770	2004-09-13 23:33:47
2410	Split	HRV	Split-Dalmatia	189388	2014-02-04 13:39:48
2411	Rijeka	HRV	Primorje-Gorski Kota	167964	2007-12-22 23:23:09
2412	Osijek	HRV	Osijek-Baranja	104761	2007-11-10 22:15:41
2413	La Habana	CUB	La Habana	2256000	2008-03-01 23:33:06
2414	Santiago de Cuba	CUB	Santiago de Cuba	433180	2005-12-21 23:22:22
2415	Camagüey	CUB	Camagüey	298726	2009-08-08 17:28:41
2416	Holguín	CUB	Holguín	249492	2003-02-04 15:05:30
2417	Santa Clara	CUB	Villa Clara	207350	2006-10-07 04:47:39
2418	Guantánamo	CUB	Guantánamo	205078	2012-11-10 05:29:06
2419	Pinar del Río	CUB	Pinar del Río	142100	2012-02-16 06:54:17
2420	Bayamo	CUB	Granma	141000	2007-05-08 10:22:07
2421	Cienfuegos	CUB	Cienfuegos	132770	2005-04-15 03:34:46
2422	Victoria de las Tunas	CUB	Las Tunas	132350	2013-06-06 23:59:16
2423	Matanzas	CUB	Matanzas	123273	2001-10-09 21:40:30
2424	Manzanillo	CUB	Granma	109350	2001-12-04 15:13:03
2425	Sancti-Spíritus	CUB	Sancti-Spíritus	100751	2013-05-27 07:02:56
2426	Ciego de Ávila	CUB	Ciego de Ávila	98505	2014-04-17 16:35:53
2427	al-Salimiya	KWT	Hawalli	130215	2009-12-16 04:33:01
2428	Jalib al-Shuyukh	KWT	Hawalli	102178	2005-08-09 08:21:06
2429	Kuwait	KWT	al-Asima	28859	2002-05-25 14:09:05
2430	Nicosia	CYP	Nicosia	195000	2009-11-15 22:18:39
2431	Limassol	CYP	Limassol	154400	2011-11-16 13:08:37
2432	Vientiane	LAO	Viangchan	531800	2007-05-01 09:10:43
2433	Savannakhet	LAO	Savannakhet	96652	2002-02-11 01:41:15
2434	Riga	LVA	Riika	764328	2005-08-11 09:13:42
2435	Daugavpils	LVA	Daugavpils	114829	2011-12-09 14:53:44
2436	Liepaja	LVA	Liepaja	89439	2012-06-20 16:36:55
2437	Maseru	LSO	Maseru	297000	2011-11-06 18:03:33
2438	Beirut	LBN	Beirut	1100000	2012-08-04 11:43:10
2439	Tripoli	LBN	al-Shamal	240000	2012-12-28 11:32:11
2440	Monrovia	LBR	Montserrado	850000	2001-06-18 22:19:58
2441	Tripoli	LBY	Tripoli	1682000	2004-11-01 19:46:14
2442	Bengasi	LBY	Bengasi	804000	2003-06-23 14:14:07
2443	Misrata	LBY	Misrata	121669	2006-11-22 19:41:43
2444	al-Zawiya	LBY	al-Zawiya	89338	2007-08-15 23:37:29
2445	Schaan	LIE	Schaan	5346	2005-03-07 05:38:29
2446	Vaduz	LIE	Vaduz	5043	2001-06-05 23:08:31
2447	Vilnius	LTU	Vilna	577969	2008-05-15 10:26:02
2448	Kaunas	LTU	Kaunas	412639	2014-06-15 23:52:37
2449	Klaipeda	LTU	Klaipeda	202451	2002-11-18 02:21:07
2450	iauliai	LTU	iauliai	146563	2010-09-05 12:42:03
2451	Panevezys	LTU	Panevezys	133695	2011-11-04 14:45:07
2452	Luxembourg [Luxemburg/Lëtzebuerg]	LUX	Luxembourg	80700	2001-08-04 07:37:46
2453	El-Aaiún	ESH	El-Aaiún	169000	2006-01-24 23:25:11
2454	Macao	MAC	Macau	437500	2002-01-15 07:23:45
2455	Antananarivo	MDG	Antananarivo	675669	2009-04-19 11:19:50
2456	Toamasina	MDG	Toamasina	127441	2003-01-09 09:06:23
2457	Antsirabé	MDG	Antananarivo	120239	2002-02-11 16:40:09
2458	Mahajanga	MDG	Mahajanga	100807	2010-06-13 12:11:40
2459	Fianarantsoa	MDG	Fianarantsoa	99005	2005-05-02 06:22:22
2460	Skopje	MKD	Skopje	444299	2006-04-13 22:38:52
2461	Blantyre	MWI	Blantyre	478155	2012-02-12 07:46:42
2462	Lilongwe	MWI	Lilongwe	435964	2007-01-06 14:45:43
2463	Male	MDV	Maale	71000	2006-10-28 23:11:21
2464	Kuala Lumpur	MYS	Wilayah Persekutuan	1297526	2008-06-17 07:45:45
2465	Ipoh	MYS	Perak	382853	2006-11-11 08:34:53
2466	Johor Baharu	MYS	Johor	328436	2008-12-14 03:32:39
2467	Petaling Jaya	MYS	Selangor	254350	2010-05-02 20:44:46
2468	Kelang	MYS	Selangor	243355	2005-09-03 08:04:35
2469	Kuala Terengganu	MYS	Terengganu	228119	2006-11-25 10:40:04
2470	Pinang	MYS	Pulau Pinang	219603	2008-07-19 01:15:03
2471	Kota Bharu	MYS	Kelantan	219582	2010-09-02 06:42:23
2472	Kuantan	MYS	Pahang	199484	2013-01-22 07:26:22
2473	Taiping	MYS	Perak	183261	2004-09-05 02:53:33
2474	Seremban	MYS	Negeri Sembilan	182869	2012-12-19 08:18:34
2475	Kuching	MYS	Sarawak	148059	2010-07-12 13:03:42
2476	Sibu	MYS	Sarawak	126381	2002-03-07 06:13:03
2477	Sandakan	MYS	Sabah	125841	2010-12-14 14:33:21
2478	Alor Setar	MYS	Kedah	124412	2007-02-24 12:02:57
2479	Selayang Baru	MYS	Selangor	124228	2004-03-24 04:26:41
2480	Sungai Petani	MYS	Kedah	114763	2008-01-18 23:45:28
2481	Shah Alam	MYS	Selangor	102019	2012-12-11 08:38:19
2482	Bamako	MLI	Bamako	809552	2008-08-08 21:57:50
2483	Birkirkara	MLT	Outer Harbour	21445	2014-02-19 01:52:29
2484	Valletta	MLT	Inner Harbour	7073	2010-06-03 04:34:23
2485	Casablanca	MAR	Casablanca	2940623	2006-12-06 11:53:52
2486	Rabat	MAR	Rabat-Salé-Zammour-Z	623457	2004-06-06 11:42:22
2487	Marrakech	MAR	Marrakech-Tensift-Al	621914	2007-03-18 13:47:13
2488	Fès	MAR	Fès-Boulemane	541162	2009-05-21 22:30:37
2489	Tanger	MAR	Tanger-Tétouan	521735	2001-11-02 07:37:35
2490	Salé	MAR	Rabat-Salé-Zammour-Z	504420	2014-12-10 12:22:01
2491	Meknès	MAR	Meknès-Tafilalet	460000	2006-11-06 15:17:54
2492	Oujda	MAR	Oriental	365382	2006-02-03 07:16:32
2493	Kénitra	MAR	Gharb-Chrarda-Béni H	292600	2009-02-16 11:41:42
2494	Tétouan	MAR	Tanger-Tétouan	277516	2006-01-01 14:05:46
2495	Safi	MAR	Doukkala-Abda	262300	2012-02-05 08:44:39
2496	Agadir	MAR	Souss Massa-Draâ	155244	2003-03-16 13:21:03
2497	Mohammedia	MAR	Casablanca	154706	2008-05-07 17:57:48
2498	Khouribga	MAR	Chaouia-Ouardigha	152090	2008-05-15 19:15:21
2499	Beni-Mellal	MAR	Tadla-Azilal	140212	2013-10-03 02:47:42
2500	Témara	MAR	Rabat-Salé-Zammour-Z	126303	2012-02-12 05:43:23
2501	El Jadida	MAR	Doukkala-Abda	119083	2013-04-26 04:09:54
2502	Nador	MAR	Oriental	112450	2005-04-27 07:10:52
2503	Ksar el Kebir	MAR	Tanger-Tétouan	107065	2014-12-19 19:39:57
2504	Settat	MAR	Chaouia-Ouardigha	96200	2008-11-01 16:37:29
2505	Taza	MAR	Taza-Al Hoceima-Taou	92700	2001-08-21 12:51:40
2506	El Araich	MAR	Tanger-Tétouan	90400	2008-05-19 20:54:22
2507	Dalap-Uliga-Darrit	MHL	Majuro	28000	2009-07-21 15:40:22
2508	Fort-de-France	MTQ	Fort-de-France	94050	2014-03-15 12:43:20
2509	Nouakchott	MRT	Nouakchott	667300	2013-06-28 20:21:45
2510	Nouâdhibou	MRT	Dakhlet Nouâdhibou	97600	2003-01-25 06:04:51
2511	Port-Louis	MUS	Port-Louis	138200	2007-03-19 04:51:36
2512	Beau Bassin-Rose Hill	MUS	Plaines Wilhelms	100616	2007-12-15 01:04:33
2513	Vacoas-Phoenix	MUS	Plaines Wilhelms	98464	2010-11-14 12:04:53
2514	Mamoutzou	MYT	Mamoutzou	12000	2006-08-22 06:39:13
2515	Ciudad de México	MEX	Distrito Federal	8591309	2004-05-16 21:45:04
2516	Guadalajara	MEX	Jalisco	1647720	2004-12-15 23:29:14
2517	Ecatepec de Morelos	MEX	México	1620303	2010-04-20 16:13:03
2518	Puebla	MEX	Puebla	1346176	2009-04-27 11:09:16
2519	Nezahualcóyotl	MEX	México	1224924	2001-04-03 06:04:03
2520	Juárez	MEX	Chihuahua	1217818	2010-02-15 23:17:09
2521	Tijuana	MEX	Baja California	1212232	2004-10-26 14:13:51
2522	León	MEX	Guanajuato	1133576	2007-02-08 01:16:32
2523	Monterrey	MEX	Nuevo León	1108499	2002-12-27 07:05:32
2524	Zapopan	MEX	Jalisco	1002239	2014-07-26 16:11:13
2525	Naucalpan de Juárez	MEX	México	857511	2013-04-05 19:33:36
2526	Mexicali	MEX	Baja California	764902	2008-08-26 23:23:13
2527	Culiacán	MEX	Sinaloa	744859	2011-01-16 14:31:53
2528	Acapulco de Juárez	MEX	Guerrero	721011	2008-06-01 19:57:24
2529	Tlalnepantla de Baz	MEX	México	720755	2012-01-25 05:58:26
2530	Mérida	MEX	Yucatán	703324	2008-02-15 23:15:09
2531	Chihuahua	MEX	Chihuahua	670208	2006-02-08 23:46:21
2532	San Luis Potosí	MEX	San Luis Potosí	669353	2005-11-08 21:11:22
2533	Guadalupe	MEX	Nuevo León	668780	2005-10-16 12:16:30
2534	Toluca	MEX	México	665617	2005-04-12 16:21:22
2535	Aguascalientes	MEX	Aguascalientes	643360	2009-05-02 06:21:11
2536	Querétaro	MEX	Querétaro de Arteaga	639839	2006-01-10 15:01:30
2537	Morelia	MEX	Michoacán de Ocampo	619958	2007-06-04 04:55:22
2538	Hermosillo	MEX	Sonora	608697	2011-09-27 08:47:18
2539	Saltillo	MEX	Coahuila de Zaragoza	577352	2014-07-07 11:37:49
2540	Torreón	MEX	Coahuila de Zaragoza	529093	2008-09-28 07:03:37
2541	Centro (Villahermosa)	MEX	Tabasco	519873	2008-05-13 13:47:49
2542	San Nicolás de los Garza	MEX	Nuevo León	495540	2014-06-04 08:27:48
2543	Durango	MEX	Durango	490524	2007-12-20 19:32:21
2544	Chimalhuacán	MEX	México	490245	2009-05-22 23:33:56
2545	Tlaquepaque	MEX	Jalisco	475472	2005-06-05 07:50:32
2546	Atizapán de Zaragoza	MEX	México	467262	2005-02-10 02:53:33
2547	Veracruz	MEX	Veracruz	457119	2002-09-27 17:08:54
2548	Cuautitlán Izcalli	MEX	México	452976	2002-11-21 01:58:45
2549	Irapuato	MEX	Guanajuato	440039	2004-08-14 08:35:25
2550	Tuxtla Gutiérrez	MEX	Chiapas	433544	2012-07-27 12:19:04
2551	Tultitlán	MEX	México	432411	2002-11-24 18:26:59
2552	Reynosa	MEX	Tamaulipas	419776	2010-11-09 20:38:51
2553	Benito Juárez	MEX	Quintana Roo	419276	2012-03-03 03:12:19
2554	Matamoros	MEX	Tamaulipas	416428	2011-10-16 17:47:21
2555	Xalapa	MEX	Veracruz	390058	2004-09-03 17:42:50
2556	Celaya	MEX	Guanajuato	382140	2008-10-17 07:04:54
2557	Mazatlán	MEX	Sinaloa	380265	2004-09-01 11:32:01
2558	Ensenada	MEX	Baja California	369573	2008-10-24 01:28:41
2559	Ahome	MEX	Sinaloa	358663	2009-08-18 11:48:35
2560	Cajeme	MEX	Sonora	355679	2003-03-05 07:06:57
2561	Cuernavaca	MEX	Morelos	337966	2014-06-01 14:29:53
2562	Tonalá	MEX	Jalisco	336109	2005-10-10 03:51:04
2563	Valle de Chalco Solidaridad	MEX	México	323113	2010-10-04 12:57:47
2564	Nuevo Laredo	MEX	Tamaulipas	310277	2008-06-15 08:34:24
2565	Tepic	MEX	Nayarit	305025	2010-12-11 15:19:27
2566	Tampico	MEX	Tamaulipas	294789	2009-10-28 23:14:48
2567	Ixtapaluca	MEX	México	293160	2008-06-11 01:09:24
2568	Apodaca	MEX	Nuevo León	282941	2003-08-19 06:17:15
2569	Guasave	MEX	Sinaloa	277201	2013-08-18 22:23:45
2570	Gómez Palacio	MEX	Durango	272806	2007-07-22 08:25:43
2571	Tapachula	MEX	Chiapas	271141	2013-06-09 02:15:28
2572	Nicolás Romero	MEX	México	269393	2006-07-01 11:34:10
2573	Coatzacoalcos	MEX	Veracruz	267037	2014-10-08 05:47:12
2574	Uruapan	MEX	Michoacán de Ocampo	265211	2006-07-28 04:26:58
2575	Victoria	MEX	Tamaulipas	262686	2010-10-23 15:48:33
2576	Oaxaca de Juárez	MEX	Oaxaca	256848	2010-01-09 08:07:11
2577	Coacalco de Berriozábal	MEX	México	252270	2002-01-07 05:21:37
2578	Pachuca de Soto	MEX	Hidalgo	244688	2002-05-13 12:27:16
2579	General Escobedo	MEX	Nuevo León	232961	2005-06-11 13:17:18
2580	Salamanca	MEX	Guanajuato	226864	2007-06-24 01:18:48
2581	Santa Catarina	MEX	Nuevo León	226573	2005-09-06 04:17:38
2582	Tehuacán	MEX	Puebla	225943	2008-04-15 18:54:35
2583	Chalco	MEX	México	222201	2003-07-19 09:21:48
2584	Cárdenas	MEX	Tabasco	216903	2009-06-03 22:43:44
2585	Campeche	MEX	Campeche	216735	2007-12-04 17:33:37
2586	La Paz	MEX	México	213045	2004-10-13 06:50:15
2587	Othón P. Blanco (Chetumal)	MEX	Quintana Roo	208014	2011-11-23 20:27:02
2588	Texcoco	MEX	México	203681	2012-02-12 10:04:23
2589	La Paz	MEX	Baja California Sur	196708	2012-01-23 11:38:49
2590	Metepec	MEX	México	194265	2007-09-27 12:22:19
2591	Monclova	MEX	Coahuila de Zaragoza	193657	2011-11-08 13:33:18
2592	Huixquilucan	MEX	México	193156	2008-08-05 19:05:20
2593	Chilpancingo de los Bravo	MEX	Guerrero	192509	2005-06-09 02:27:11
2594	Puerto Vallarta	MEX	Jalisco	183741	2006-11-06 18:27:22
2595	Fresnillo	MEX	Zacatecas	182744	2010-01-17 23:01:19
2596	Ciudad Madero	MEX	Tamaulipas	182012	2013-09-12 22:22:01
2597	Soledad de Graciano Sánchez	MEX	San Luis Potosí	179956	2012-02-04 23:45:06
2598	San Juan del Río	MEX	Querétaro	179300	2009-11-09 04:33:26
2599	San Felipe del Progreso	MEX	México	177330	2014-01-18 01:38:41
2600	Córdoba	MEX	Veracruz	176952	2002-01-12 20:59:33
2601	Tecámac	MEX	México	172410	2010-12-17 22:48:44
2602	Ocosingo	MEX	Chiapas	171495	2006-08-27 22:13:44
2603	Carmen	MEX	Campeche	171367	2005-11-16 23:38:26
2604	Lázaro Cárdenas	MEX	Michoacán de Ocampo	170878	2012-11-02 17:19:51
2605	Jiutepec	MEX	Morelos	170428	2001-02-15 08:52:45
2606	Papantla	MEX	Veracruz	170123	2001-06-01 08:02:34
2607	Comalcalco	MEX	Tabasco	164640	2007-02-06 04:45:10
2608	Zamora	MEX	Michoacán de Ocampo	161191	2004-03-12 15:31:01
2609	Nogales	MEX	Sonora	159103	2006-08-14 10:28:59
2610	Huimanguillo	MEX	Tabasco	158335	2005-08-25 18:54:54
2611	Cuautla	MEX	Morelos	153132	2004-10-07 11:10:55
2612	Minatitlán	MEX	Veracruz	152983	2007-08-21 01:49:41
2613	Poza Rica de Hidalgo	MEX	Veracruz	152678	2009-03-16 18:42:19
2614	Ciudad Valles	MEX	San Luis Potosí	146411	2006-06-18 11:49:13
2615	Navolato	MEX	Sinaloa	145396	2008-06-08 09:45:21
2616	San Luis Río Colorado	MEX	Sonora	145276	2014-06-08 23:05:49
2617	Pénjamo	MEX	Guanajuato	143927	2012-06-21 23:42:53
2618	San Andrés Tuxtla	MEX	Veracruz	142251	2012-03-21 21:30:28
2619	Guanajuato	MEX	Guanajuato	141215	2002-10-08 11:34:29
2620	Navojoa	MEX	Sonora	140495	2012-08-20 09:57:14
2621	Zitácuaro	MEX	Michoacán de Ocampo	137970	2011-06-23 01:38:16
2622	Boca del Río	MEX	Veracruz-Llave	135721	2006-06-16 08:48:12
2623	Allende	MEX	Guanajuato	134645	2012-02-26 15:23:47
2624	Silao	MEX	Guanajuato	134037	2005-10-05 05:27:04
2625	Macuspana	MEX	Tabasco	133795	2012-06-19 03:59:24
2626	San Juan Bautista Tuxtepec	MEX	Oaxaca	133675	2008-12-06 08:56:28
2627	San Cristóbal de las Casas	MEX	Chiapas	132317	2007-04-03 03:55:43
2628	Valle de Santiago	MEX	Guanajuato	130557	2006-10-11 20:19:26
2629	Guaymas	MEX	Sonora	130108	2004-06-13 22:18:30
2630	Colima	MEX	Colima	129454	2004-09-18 02:13:18
2631	Dolores Hidalgo	MEX	Guanajuato	128675	2002-08-14 17:26:24
2632	Lagos de Moreno	MEX	Jalisco	127949	2012-11-27 05:53:45
2633	Piedras Negras	MEX	Coahuila de Zaragoza	127898	2002-05-13 23:22:24
2634	Altamira	MEX	Tamaulipas	127490	2010-10-04 09:59:19
2635	Túxpam	MEX	Veracruz	126475	2012-12-28 08:11:07
2636	San Pedro Garza García	MEX	Nuevo León	126147	2004-05-14 09:36:20
2637	Cuauhtémoc	MEX	Chihuahua	124279	2002-01-09 16:26:33
2638	Manzanillo	MEX	Colima	124014	2004-02-12 05:38:40
2639	Iguala de la Independencia	MEX	Guerrero	123883	2003-04-27 13:47:26
2640	Zacatecas	MEX	Zacatecas	123700	2005-08-26 06:53:07
2641	Tlajomulco de Zúñiga	MEX	Jalisco	123220	2003-12-07 01:15:59
2642	Tulancingo de Bravo	MEX	Hidalgo	121946	2001-06-23 06:15:19
2643	Zinacantepec	MEX	México	121715	2009-10-20 17:23:26
2644	San Martín Texmelucan	MEX	Puebla	121093	2002-09-14 20:09:03
2645	Tepatitlán de Morelos	MEX	Jalisco	118948	2008-03-02 08:58:48
2646	Martínez de la Torre	MEX	Veracruz	118815	2009-04-06 11:58:56
2647	Orizaba	MEX	Veracruz	118488	2005-12-06 07:06:36
2648	Apatzingán	MEX	Michoacán de Ocampo	117849	2011-03-01 16:09:45
2649	Atlixco	MEX	Puebla	117019	2007-12-08 02:51:24
2650	Delicias	MEX	Chihuahua	116132	2008-01-17 16:36:31
2651	Ixtlahuaca	MEX	México	115548	2014-05-12 05:44:31
2652	El Mante	MEX	Tamaulipas	112453	2005-04-26 17:05:43
2653	Lerdo	MEX	Durango	112272	2014-09-07 23:48:30
2654	Almoloya de Juárez	MEX	México	110550	2012-05-14 15:27:53
2655	Acámbaro	MEX	Guanajuato	110487	2010-11-08 05:08:58
2656	Acuña	MEX	Coahuila de Zaragoza	110388	2001-11-19 22:18:22
2657	Guadalupe	MEX	Zacatecas	108881	2001-11-11 05:52:43
2658	Huejutla de Reyes	MEX	Hidalgo	108017	2012-05-13 23:59:21
2659	Hidalgo	MEX	Michoacán de Ocampo	106198	2010-04-15 22:46:57
2660	Los Cabos	MEX	Baja California Sur	105199	2008-08-22 23:42:27
2661	Comitán de Domínguez	MEX	Chiapas	104986	2004-12-16 17:19:41
2662	Cunduacán	MEX	Tabasco	104164	2013-11-18 04:30:27
2663	Río Bravo	MEX	Tamaulipas	103901	2013-04-12 20:31:11
2664	Temapache	MEX	Veracruz	102824	2005-10-13 21:31:13
2665	Chilapa de Alvarez	MEX	Guerrero	102716	2008-09-20 15:22:30
2666	Hidalgo del Parral	MEX	Chihuahua	100881	2012-09-17 13:08:32
2667	San Francisco del Rincón	MEX	Guanajuato	100149	2004-12-17 23:05:16
2668	Taxco de Alarcón	MEX	Guerrero	99907	2013-10-18 17:30:46
2669	Zumpango	MEX	México	99781	2003-07-24 07:51:33
2670	San Pedro Cholula	MEX	Puebla	99734	2009-12-06 19:48:19
2671	Lerma	MEX	México	99714	2008-03-09 12:04:52
2672	Tecomán	MEX	Colima	99296	2002-12-26 10:57:40
2673	Las Margaritas	MEX	Chiapas	97389	2001-09-14 11:53:49
2674	Cosoleacaque	MEX	Veracruz	97199	2014-06-20 13:30:10
2675	San Luis de la Paz	MEX	Guanajuato	96763	2005-11-03 19:33:25
2676	José Azueta	MEX	Guerrero	95448	2001-10-20 23:46:04
2677	Santiago Ixcuintla	MEX	Nayarit	95311	2009-06-15 16:06:26
2678	San Felipe	MEX	Guanajuato	95305	2013-07-05 11:31:47
2679	Tejupilco	MEX	México	94934	2003-12-04 06:41:09
2680	Tantoyuca	MEX	Veracruz	94709	2014-05-02 13:50:08
2681	Salvatierra	MEX	Guanajuato	94322	2003-05-09 12:17:43
2682	Tultepec	MEX	México	93364	2001-11-16 08:34:42
2683	Temixco	MEX	Morelos	92686	2014-02-13 18:46:44
2684	Matamoros	MEX	Coahuila de Zaragoza	91858	2005-07-25 18:40:36
2685	Pánuco	MEX	Veracruz	90551	2011-02-14 05:56:02
2686	El Fuerte	MEX	Sinaloa	89556	2014-05-01 19:29:49
2687	Tierra Blanca	MEX	Veracruz	89143	2008-05-06 08:47:52
2688	Weno	FSM	Chuuk	22000	2007-03-16 18:46:55
2689	Palikir	FSM	Pohnpei	8600	2012-07-20 18:46:14
2690	Chisinau	MDA	Chisinau	719900	2003-03-23 11:34:55
2691	Tiraspol	MDA	Dnjestria	194300	2011-04-18 08:22:48
2692	Balti	MDA	Balti	153400	2009-11-20 23:04:43
2693	Bender (Tîghina)	MDA	Bender (Tîghina)	125700	2010-07-07 02:31:07
2694	Monte-Carlo	MCO		13154	2010-07-14 02:26:28
2695	Monaco-Ville	MCO		1234	2012-09-22 22:32:33
2696	Ulan Bator	MNG	Ulaanbaatar	773700	2009-09-20 18:25:50
2697	Plymouth	MSR	Plymouth	2000	2001-11-10 05:33:21
2698	Maputo	MOZ	Maputo	1018938	2008-05-26 20:12:33
2699	Matola	MOZ	Maputo	424662	2009-05-15 09:40:17
2700	Beira	MOZ	Sofala	397368	2001-03-22 05:22:15
2701	Nampula	MOZ	Nampula	303346	2011-05-02 03:11:20
2702	Chimoio	MOZ	Manica	171056	2002-12-06 09:14:28
2703	Naçala-Porto	MOZ	Nampula	158248	2003-05-18 14:29:22
2704	Quelimane	MOZ	Zambézia	150116	2005-10-11 01:39:11
2705	Mocuba	MOZ	Zambézia	124700	2002-08-10 20:39:59
2706	Tete	MOZ	Tete	101984	2010-11-04 09:14:41
2707	Xai-Xai	MOZ	Gaza	99442	2008-08-04 11:46:12
2708	Gurue	MOZ	Zambézia	99300	2004-12-01 02:08:34
2709	Maxixe	MOZ	Inhambane	93985	2011-04-16 09:34:44
2710	Rangoon (Yangon)	MMR	Rangoon [Yangon]	3361700	2013-10-28 19:54:07
2711	Mandalay	MMR	Mandalay	885300	2003-11-27 23:27:29
2712	Moulmein (Mawlamyine)	MMR	Mon	307900	2010-02-14 15:52:31
2713	Pegu (Bago)	MMR	Pegu [Bago]	190900	2008-12-14 22:36:04
2714	Bassein (Pathein)	MMR	Irrawaddy [Ayeyarwad	183900	2004-06-20 21:46:14
2715	Monywa	MMR	Sagaing	138600	2003-11-17 20:33:52
2716	Sittwe (Akyab)	MMR	Rakhine	137600	2011-03-25 14:52:58
2717	Taunggyi (Taunggye)	MMR	Shan	131500	2006-11-03 06:58:48
2718	Meikhtila	MMR	Mandalay	129700	2013-06-17 10:41:27
2719	Mergui (Myeik)	MMR	Tenasserim [Tanintha	122700	2008-01-22 21:29:14
2720	Lashio (Lasho)	MMR	Shan	107600	2004-11-28 11:05:25
2721	Prome (Pyay)	MMR	Pegu [Bago]	105700	2012-07-18 16:21:58
2722	Henzada (Hinthada)	MMR	Irrawaddy [Ayeyarwad	104700	2004-08-08 05:28:09
2723	Myingyan	MMR	Mandalay	103600	2002-04-19 10:05:01
2724	Tavoy (Dawei)	MMR	Tenasserim [Tanintha	96800	2011-05-06 22:31:07
2725	Pagakku (Pakokku)	MMR	Magwe [Magway]	94800	2011-07-12 19:06:20
2726	Windhoek	NAM	Khomas	169000	2012-12-18 13:41:03
2727	Yangor	NRU		4050	2006-11-03 10:02:43
2728	Yaren	NRU		559	2004-08-13 16:31:15
2729	Kathmandu	NPL	Central	591835	2002-05-02 21:44:43
2730	Biratnagar	NPL	Eastern	157764	2001-08-11 17:44:46
2731	Pokhara	NPL	Western	146318	2011-10-15 10:52:07
2732	Lalitapur	NPL	Central	145847	2010-05-01 20:30:39
2733	Birgunj	NPL	Central	90639	2014-02-28 03:27:35
2734	Managua	NIC	Managua	959000	2007-12-14 15:43:04
2735	León	NIC	León	123865	2011-04-18 23:23:45
2736	Chinandega	NIC	Chinandega	97387	2013-11-17 10:30:01
2737	Masaya	NIC	Masaya	88971	2006-03-08 01:38:12
2738	Niamey	NER	Niamey	420000	2005-08-04 12:47:49
2739	Zinder	NER	Zinder	120892	2013-12-10 14:17:33
2740	Maradi	NER	Maradi	112965	2006-03-23 19:09:46
2741	Lagos	NGA	Lagos	1518000	2013-10-09 11:31:02
2742	Ibadan	NGA	Oyo & Osun	1432000	2011-03-10 09:03:32
2743	Ogbomosho	NGA	Oyo & Osun	730000	2001-06-08 22:11:50
2744	Kano	NGA	Kano & Jigawa	674100	2003-08-27 09:44:35
2745	Oshogbo	NGA	Oyo & Osun	476800	2014-04-09 17:39:25
2746	Ilorin	NGA	Kwara & Kogi	475800	2014-09-09 06:12:42
2747	Abeokuta	NGA	Ogun	427400	2009-09-14 22:12:23
2748	Port Harcourt	NGA	Rivers & Bayelsa	410000	2009-04-15 23:30:57
2749	Zaria	NGA	Kaduna	379200	2008-05-14 02:20:46
2750	Ilesha	NGA	Oyo & Osun	378400	2002-11-07 03:51:33
2751	Onitsha	NGA	Anambra & Enugu & Eb	371900	2010-05-02 07:13:52
2752	Iwo	NGA	Oyo & Osun	362000	2003-02-17 05:37:32
2753	Ado-Ekiti	NGA	Ondo & Ekiti	359400	2005-11-14 14:46:10
2754	Abuja	NGA	Federal Capital Dist	350100	2014-10-14 04:56:23
2755	Kaduna	NGA	Kaduna	342200	2014-11-27 23:23:08
2756	Mushin	NGA	Lagos	333200	2011-05-24 23:32:35
2757	Maiduguri	NGA	Borno & Yobe	320000	2011-08-22 07:33:11
2758	Enugu	NGA	Anambra & Enugu & Eb	316100	2009-09-23 23:14:25
2759	Ede	NGA	Oyo & Osun	307100	2009-04-05 12:59:44
2760	Aba	NGA	Imo & Abia	298900	2003-10-04 23:53:13
2761	Ife	NGA	Oyo & Osun	296800	2004-11-21 04:12:40
2762	Ila	NGA	Oyo & Osun	264000	2011-06-25 05:37:41
2763	Oyo	NGA	Oyo & Osun	256400	2010-09-25 07:38:20
2764	Ikerre	NGA	Ondo & Ekiti	244600	2013-08-27 01:48:43
2765	Benin City	NGA	Edo & Delta	229400	2005-08-15 23:51:39
2766	Iseyin	NGA	Oyo & Osun	217300	2001-01-18 04:52:29
2767	Katsina	NGA	Katsina	206500	2003-02-06 08:41:01
2768	Jos	NGA	Plateau & Nassarawa	206300	2012-05-12 16:43:45
2769	Sokoto	NGA	Sokoto & Kebbi & Zam	204900	2001-04-21 23:51:39
2770	Ilobu	NGA	Oyo & Osun	199000	2009-03-13 17:32:45
2771	Offa	NGA	Kwara & Kogi	197200	2002-12-08 01:45:43
2772	Ikorodu	NGA	Lagos	184900	2013-08-26 12:52:53
2773	Ilawe-Ekiti	NGA	Ondo & Ekiti	184500	2011-09-06 05:06:40
2774	Owo	NGA	Ondo & Ekiti	183500	2010-06-11 20:27:44
2775	Ikirun	NGA	Oyo & Osun	181400	2003-02-06 22:36:34
2776	Shaki	NGA	Oyo & Osun	174500	2005-12-27 07:29:05
2777	Calabar	NGA	Cross River	174400	2010-12-18 12:33:04
2778	Ondo	NGA	Ondo & Ekiti	173600	2001-01-21 23:21:50
2779	Akure	NGA	Ondo & Ekiti	162300	2006-10-04 15:47:22
2780	Gusau	NGA	Sokoto & Kebbi & Zam	158000	2012-09-05 19:31:50
2781	Ijebu-Ode	NGA	Ogun	156400	2002-12-02 01:39:39
2782	Effon-Alaiye	NGA	Oyo & Osun	153100	2013-03-10 17:22:18
2783	Kumo	NGA	Bauchi & Gombe	148000	2008-10-08 13:56:11
2784	Shomolu	NGA	Lagos	147700	2012-03-20 08:51:46
2785	Oka-Akoko	NGA	Ondo & Ekiti	142900	2010-11-04 06:45:42
2786	Ikare	NGA	Ondo & Ekiti	140800	2005-03-13 20:57:46
2787	Sapele	NGA	Edo & Delta	139200	2005-04-22 08:56:51
2788	Deba Habe	NGA	Bauchi & Gombe	138600	2013-12-25 09:30:36
2789	Minna	NGA	Niger	136900	2005-08-17 22:07:58
2790	Warri	NGA	Edo & Delta	126100	2003-06-16 19:57:04
2791	Bida	NGA	Niger	125500	2003-09-23 23:47:45
2792	Ikire	NGA	Oyo & Osun	123300	2013-06-13 04:04:34
2793	Makurdi	NGA	Benue	123100	2009-08-20 14:19:03
2794	Lafia	NGA	Plateau & Nassarawa	122500	2002-11-24 15:19:33
2795	Inisa	NGA	Oyo & Osun	119800	2003-01-21 03:27:48
2796	Shagamu	NGA	Ogun	117200	2011-05-26 23:11:09
2797	Awka	NGA	Anambra & Enugu & Eb	111200	2003-06-07 12:13:49
2798	Gombe	NGA	Bauchi & Gombe	107800	2001-12-28 13:49:54
2799	Igboho	NGA	Oyo & Osun	106800	2011-11-18 19:41:04
2800	Ejigbo	NGA	Oyo & Osun	105900	2004-01-20 11:36:41
2801	Agege	NGA	Lagos	105000	2008-12-05 19:45:08
2802	Ise-Ekiti	NGA	Ondo & Ekiti	103400	2012-03-11 20:13:20
2803	Ugep	NGA	Cross River	102600	2006-07-21 08:47:02
2804	Epe	NGA	Lagos	101000	2012-12-28 20:06:58
2805	Alofi	NIU		682	2011-03-02 02:39:56
2806	Kingston	NFK		800	2009-11-03 12:53:40
2807	Oslo	NOR	Oslo	508726	2001-01-05 22:47:21
2808	Bergen	NOR	Hordaland	230948	2008-05-01 02:46:01
2809	Trondheim	NOR	Sør-Trøndelag	150166	2013-01-22 11:38:20
2810	Stavanger	NOR	Rogaland	108848	2011-10-03 23:53:31
2811	Bærum	NOR	Akershus	101340	2010-05-08 08:46:39
2812	Abidjan	CIV	Abidjan	2500000	2010-07-02 20:26:38
2813	Bouaké	CIV	Bouaké	329850	2010-03-28 12:12:38
2814	Yamoussoukro	CIV	Yamoussoukro	130000	2008-05-02 02:01:19
2815	Daloa	CIV	Daloa	121842	2011-01-18 23:56:53
2816	Korhogo	CIV	Korhogo	109445	2005-04-26 12:11:19
2817	al-Sib	OMN	Masqat	155000	2001-04-02 14:44:44
2818	Salala	OMN	Zufar	131813	2002-04-09 11:57:19
2819	Bawshar	OMN	Masqat	107500	2009-07-15 17:15:48
2820	Suhar	OMN	al-Batina	90814	2010-11-05 10:58:55
2821	Masqat	OMN	Masqat	51969	2013-04-18 04:32:14
2822	Karachi	PAK	Sindh	9269265	2008-03-21 20:31:49
2823	Lahore	PAK	Punjab	5063499	2014-08-07 23:42:55
2824	Faisalabad	PAK	Punjab	1977246	2001-04-03 17:12:12
2825	Rawalpindi	PAK	Punjab	1406214	2009-07-24 03:10:54
2826	Multan	PAK	Punjab	1182441	2013-12-22 14:43:57
2827	Hyderabad	PAK	Sindh	1151274	2007-01-08 14:23:47
2828	Gujranwala	PAK	Punjab	1124749	2013-09-12 09:35:30
2829	Peshawar	PAK	Nothwest Border Prov	988005	2013-02-16 06:26:20
2830	Quetta	PAK	Baluchistan	560307	2003-08-26 12:23:42
2831	Islamabad	PAK	Islamabad	524500	2009-11-22 03:56:07
2832	Sargodha	PAK	Punjab	455360	2013-05-28 13:22:58
2833	Sialkot	PAK	Punjab	417597	2001-06-14 04:25:23
2834	Bahawalpur	PAK	Punjab	403408	2002-03-02 02:56:08
2835	Sukkur	PAK	Sindh	329176	2007-11-17 23:34:07
2836	Jhang	PAK	Punjab	292214	2004-06-18 19:07:14
2837	Sheikhupura	PAK	Punjab	271875	2011-05-02 04:41:34
2838	Larkana	PAK	Sindh	270366	2011-08-01 23:58:31
2839	Gujrat	PAK	Punjab	250121	2013-09-16 03:49:29
2840	Mardan	PAK	Nothwest Border Prov	244511	2003-05-06 12:21:13
2841	Kasur	PAK	Punjab	241649	2003-04-24 23:14:22
2842	Rahim Yar Khan	PAK	Punjab	228479	2014-01-04 22:51:42
2843	Sahiwal	PAK	Punjab	207388	2005-07-24 05:10:14
2844	Okara	PAK	Punjab	200901	2003-12-11 20:09:20
2845	Wah	PAK	Punjab	198400	2012-05-05 11:59:29
2846	Dera Ghazi Khan	PAK	Punjab	188100	2013-07-27 04:27:10
2847	Mirpur Khas	PAK	Sind	184500	2011-11-14 04:54:53
2848	Nawabshah	PAK	Sind	183100	2008-11-15 09:17:14
2849	Mingora	PAK	Nothwest Border Prov	174500	2011-06-05 16:53:51
2850	Chiniot	PAK	Punjab	169300	2006-05-15 21:32:22
2851	Kamoke	PAK	Punjab	151000	2001-03-09 02:46:30
2852	Mandi Burewala	PAK	Punjab	149900	2012-04-11 17:47:02
2853	Jhelum	PAK	Punjab	145800	2002-07-09 16:44:44
2854	Sadiqabad	PAK	Punjab	141500	2013-07-18 16:55:23
2855	Jacobabad	PAK	Sind	137700	2001-10-14 06:16:27
2856	Shikarpur	PAK	Sind	133300	2008-08-13 15:23:44
2857	Khanewal	PAK	Punjab	133000	2007-12-18 11:28:56
2858	Hafizabad	PAK	Punjab	130200	2011-06-13 01:23:37
2859	Kohat	PAK	Nothwest Border Prov	125300	2006-02-04 23:49:14
2860	Muzaffargarh	PAK	Punjab	121600	2006-10-08 01:02:16
2861	Khanpur	PAK	Punjab	117800	2002-10-24 02:28:28
2862	Gojra	PAK	Punjab	115000	2006-04-25 23:18:29
2863	Bahawalnagar	PAK	Punjab	109600	2014-02-17 11:12:54
2864	Muridke	PAK	Punjab	108600	2014-05-08 09:23:25
2865	Pak Pattan	PAK	Punjab	107800	2001-05-16 02:19:14
2866	Abottabad	PAK	Nothwest Border Prov	106000	2011-06-12 22:31:56
2867	Tando Adam	PAK	Sind	103400	2003-07-25 22:30:48
2868	Jaranwala	PAK	Punjab	103300	2007-06-03 07:15:13
2869	Khairpur	PAK	Sind	102200	2002-04-05 08:34:58
2870	Chishtian Mandi	PAK	Punjab	101700	2004-08-23 06:23:01
2871	Daska	PAK	Punjab	101500	2014-06-01 06:53:19
2872	Dadu	PAK	Sind	98600	2011-02-26 18:17:51
2873	Mandi Bahauddin	PAK	Punjab	97300	2012-06-24 21:26:25
2874	Ahmadpur East	PAK	Punjab	96000	2003-05-12 22:38:03
2875	Kamalia	PAK	Punjab	95300	2012-11-13 22:11:34
2876	Khuzdar	PAK	Baluchistan	93100	2013-07-07 13:37:44
2877	Vihari	PAK	Punjab	92300	2012-11-17 13:01:38
2878	Dera Ismail Khan	PAK	Nothwest Border Prov	90400	2001-01-23 04:42:50
2879	Wazirabad	PAK	Punjab	89700	2007-02-27 15:57:35
2880	Nowshera	PAK	Nothwest Border Prov	89400	2006-09-25 11:04:31
2881	Koror	PLW	Koror	12000	2007-09-06 04:21:59
2882	Ciudad de Panamá	PAN	Panamá	471373	2006-03-17 04:28:51
2883	San Miguelito	PAN	San Miguelito	315382	2012-04-22 08:54:57
2884	Port Moresby	PNG	National Capital Dis	247000	2008-11-17 15:39:32
2885	Asunción	PRY	Asunción	557776	2008-04-25 06:19:34
2886	Ciudad del Este	PRY	Alto Paraná	133881	2001-08-06 18:33:02
2887	San Lorenzo	PRY	Central	133395	2005-03-06 07:18:32
2888	Lambaré	PRY	Central	99681	2001-06-01 10:50:26
2889	Fernando de la Mora	PRY	Central	95287	2001-10-21 10:37:17
2890	Lima	PER	Lima	6464693	2009-03-04 18:33:45
2891	Arequipa	PER	Arequipa	762000	2013-02-12 10:09:16
2892	Trujillo	PER	La Libertad	652000	2007-02-08 15:17:38
2893	Chiclayo	PER	Lambayeque	517000	2008-09-05 13:23:34
2894	Callao	PER	Callao	424294	2009-08-07 23:10:30
2895	Iquitos	PER	Loreto	367000	2011-09-19 09:20:08
2896	Chimbote	PER	Ancash	336000	2009-04-11 14:18:11
2897	Huancayo	PER	Junín	327000	2006-04-03 21:15:37
2898	Piura	PER	Piura	325000	2007-04-23 18:02:22
2899	Cusco	PER	Cusco	291000	2007-03-23 08:21:22
2900	Pucallpa	PER	Ucayali	220866	2008-02-24 06:54:23
2901	Tacna	PER	Tacna	215683	2009-07-07 14:09:04
2902	Ica	PER	Ica	194820	2010-12-17 03:31:08
2903	Sullana	PER	Piura	147361	2009-03-25 02:17:44
2904	Juliaca	PER	Puno	142576	2002-10-27 12:31:43
2905	Huánuco	PER	Huanuco	129688	2006-01-17 02:05:33
2906	Ayacucho	PER	Ayacucho	118960	2003-12-09 08:12:51
2907	Chincha Alta	PER	Ica	110016	2004-08-14 16:38:27
2908	Cajamarca	PER	Cajamarca	108009	2010-12-20 23:34:18
2909	Puno	PER	Puno	101578	2002-04-03 20:33:19
2910	Ventanilla	PER	Callao	101056	2002-08-24 23:21:13
2911	Castilla	PER	Piura	90642	2013-05-12 13:08:11
2912	Adamstown	PCN		42	2009-06-03 16:52:06
2913	Garapan	MNP	Saipan	9200	2001-12-22 15:22:54
2914	Lisboa	PRT	Lisboa	563210	2002-01-09 20:58:22
2915	Porto	PRT	Porto	273060	2008-07-17 16:57:42
2916	Amadora	PRT	Lisboa	122106	2009-04-14 15:10:31
2917	Coímbra	PRT	Coímbra	96100	2003-10-11 11:42:18
2918	Braga	PRT	Braga	90535	2004-08-27 03:52:20
2919	San Juan	PRI	San Juan	434374	2007-12-03 23:48:18
2920	Bayamón	PRI	Bayamón	224044	2004-09-24 14:26:30
2921	Ponce	PRI	Ponce	186475	2011-09-22 15:33:44
2922	Carolina	PRI	Carolina	186076	2001-09-15 20:50:50
2923	Caguas	PRI	Caguas	140502	2013-11-20 11:23:58
2924	Arecibo	PRI	Arecibo	100131	2006-03-11 23:19:05
2925	Guaynabo	PRI	Guaynabo	100053	2008-09-25 22:28:12
2926	Mayagüez	PRI	Mayagüez	98434	2006-07-13 14:49:02
2927	Toa Baja	PRI	Toa Baja	94085	2011-07-01 22:01:08
2928	Warszawa	POL	Mazowieckie	1615369	2014-05-01 21:03:52
2929	Lódz	POL	Lodzkie	800110	2012-03-03 23:16:41
2930	Kraków	POL	Malopolskie	738150	2004-12-22 10:32:40
2931	Wroclaw	POL	Dolnoslaskie	636765	2001-07-04 12:22:08
2932	Poznan	POL	Wielkopolskie	576899	2005-08-18 04:27:32
2933	Gdansk	POL	Pomorskie	458988	2008-01-19 01:23:16
2934	Szczecin	POL	Zachodnio-Pomorskie	416988	2010-07-20 16:25:33
2935	Bydgoszcz	POL	Kujawsko-Pomorskie	386855	2001-12-25 15:10:22
2936	Lublin	POL	Lubelskie	356251	2004-06-24 23:13:13
2937	Katowice	POL	Slaskie	345934	2001-11-02 20:48:33
2938	Bialystok	POL	Podlaskie	283937	2013-08-15 05:28:43
2939	Czestochowa	POL	Slaskie	257812	2004-03-22 09:20:49
2940	Gdynia	POL	Pomorskie	253521	2007-07-28 17:25:07
2941	Sosnowiec	POL	Slaskie	244102	2002-10-20 23:01:50
2942	Radom	POL	Mazowieckie	232262	2002-12-04 20:30:20
2943	Kielce	POL	Swietokrzyskie	212383	2006-02-20 20:33:15
2944	Gliwice	POL	Slaskie	212164	2002-11-22 22:57:05
2945	Torun	POL	Kujawsko-Pomorskie	206158	2010-09-08 07:58:02
2946	Bytom	POL	Slaskie	205560	2008-03-13 02:23:20
2947	Zabrze	POL	Slaskie	200177	2008-12-02 11:03:57
2948	Bielsko-Biala	POL	Slaskie	180307	2011-03-27 23:49:17
2949	Olsztyn	POL	Warminsko-Mazurskie	170904	2004-12-25 08:12:10
2950	Rzeszów	POL	Podkarpackie	162049	2001-09-11 14:37:47
2951	Ruda Slaska	POL	Slaskie	159665	2006-05-09 04:01:52
2952	Rybnik	POL	Slaskie	144582	2010-09-17 01:53:29
2953	Walbrzych	POL	Dolnoslaskie	136923	2010-03-05 10:10:49
2954	Tychy	POL	Slaskie	133178	2011-09-18 05:38:54
2955	Dabrowa Górnicza	POL	Slaskie	131037	2012-02-19 14:58:28
2956	Plock	POL	Mazowieckie	131011	2009-11-22 04:19:27
2957	Elblag	POL	Warminsko-Mazurskie	129782	2001-03-06 14:22:24
2958	Opole	POL	Opolskie	129553	2001-09-08 13:40:30
2959	Gorzów Wielkopolski	POL	Lubuskie	126019	2010-12-24 23:47:26
2960	Wloclawek	POL	Kujawsko-Pomorskie	123373	2009-11-23 08:09:50
2961	Chorzów	POL	Slaskie	121708	2003-03-17 08:52:47
2962	Tarnów	POL	Malopolskie	121494	2009-09-09 14:06:44
2963	Zielona Góra	POL	Lubuskie	118182	2005-11-11 22:23:49
2964	Koszalin	POL	Zachodnio-Pomorskie	112375	2014-09-26 13:08:56
2965	Legnica	POL	Dolnoslaskie	109335	2005-09-22 11:01:15
2966	Kalisz	POL	Wielkopolskie	106641	2014-03-18 17:09:11
2967	Grudziadz	POL	Kujawsko-Pomorskie	102434	2003-06-12 05:01:14
2968	Slupsk	POL	Pomorskie	102370	2004-02-14 13:58:29
2969	Jastrzebie-Zdrój	POL	Slaskie	102294	2007-11-23 18:51:14
2970	Jaworzno	POL	Slaskie	97929	2013-09-02 06:25:05
2971	Jelenia Góra	POL	Dolnoslaskie	93901	2007-09-09 22:15:58
2972	Malabo	GNQ	Bioko	40000	2005-10-24 11:34:57
2973	Doha	QAT	Doha	355000	2013-12-24 07:02:56
2974	Paris	FRA	Île-de-France	2125246	2006-09-21 15:46:57
2975	Marseille	FRA	Provence-Alpes-Côte	798430	2014-08-17 22:42:56
2976	Lyon	FRA	Rhône-Alpes	445452	2008-03-08 06:13:20
2977	Toulouse	FRA	Midi-Pyrénées	390350	2009-08-21 02:01:15
2978	Nice	FRA	Provence-Alpes-Côte	342738	2005-08-17 14:33:58
2979	Nantes	FRA	Pays de la Loire	270251	2005-09-15 12:31:28
2980	Strasbourg	FRA	Alsace	264115	2006-05-24 04:14:41
2981	Montpellier	FRA	Languedoc-Roussillon	225392	2011-01-27 19:26:10
2982	Bordeaux	FRA	Aquitaine	215363	2001-03-14 17:19:57
2983	Rennes	FRA	Haute-Normandie	206229	2004-05-19 09:25:50
2984	Le Havre	FRA	Champagne-Ardenne	190905	2001-02-07 09:23:23
2985	Reims	FRA	Nord-Pas-de-Calais	187206	2006-08-11 09:39:50
2986	Lille	FRA	Rhône-Alpes	184657	2005-11-25 18:16:43
2987	St-Étienne	FRA	Bretagne	180210	2010-09-08 12:52:05
2988	Toulon	FRA	Provence-Alpes-Côte	160639	2014-05-06 05:13:23
2989	Grenoble	FRA	Rhône-Alpes	153317	2014-12-12 18:42:50
2990	Angers	FRA	Pays de la Loire	151279	2011-12-24 13:27:05
2991	Dijon	FRA	Bourgogne	149867	2001-05-06 17:11:05
2992	Brest	FRA	Bretagne	149634	2012-07-19 07:27:46
2993	Le Mans	FRA	Pays de la Loire	146105	2014-07-02 15:20:08
2994	Clermont-Ferrand	FRA	Auvergne	137140	2004-09-17 09:54:08
2995	Amiens	FRA	Picardie	135501	2007-10-13 14:15:21
2996	Aix-en-Provence	FRA	Provence-Alpes-Côte	134222	2013-11-11 09:04:22
2997	Limoges	FRA	Limousin	133968	2005-06-21 14:58:36
2998	Nîmes	FRA	Languedoc-Roussillon	133424	2013-09-03 04:44:44
2999	Tours	FRA	Centre	132820	2011-08-16 18:17:01
3000	Villeurbanne	FRA	Rhône-Alpes	124215	2004-02-04 14:36:13
3001	Metz	FRA	Lorraine	123776	2001-12-03 04:29:28
3002	Besançon	FRA	Franche-Comté	117733	2005-02-18 05:40:32
3003	Caen	FRA	Basse-Normandie	113987	2001-04-10 03:31:28
3004	Orléans	FRA	Centre	113126	2010-10-08 23:57:26
3005	Mulhouse	FRA	Alsace	110359	2009-11-10 15:34:33
3006	Rouen	FRA	Haute-Normandie	106592	2002-10-08 06:08:15
3007	Boulogne-Billancourt	FRA	Île-de-France	106367	2009-07-18 10:36:33
3008	Perpignan	FRA	Languedoc-Roussillon	105115	2001-09-19 15:28:08
3009	Nancy	FRA	Lorraine	103605	2005-10-07 18:15:49
3010	Roubaix	FRA	Nord-Pas-de-Calais	96984	2006-04-18 18:41:12
3011	Argenteuil	FRA	Île-de-France	93961	2007-03-16 04:16:07
3012	Tourcoing	FRA	Nord-Pas-de-Calais	93540	2011-03-10 18:23:53
3013	Montreuil	FRA	Île-de-France	90674	2001-08-28 21:20:30
3014	Cayenne	GUF	Cayenne	50699	2013-07-19 08:22:24
3015	Faaa	PYF	Tahiti	25888	2001-09-25 19:25:16
3016	Papeete	PYF	Tahiti	25553	2008-03-22 23:48:29
3017	Saint-Denis	REU	Saint-Denis	131480	2014-03-25 05:15:37
3018	Bucuresti	ROM	Bukarest	2016131	2012-10-23 13:55:14
3019	Iasi	ROM	Iasi	348070	2005-11-13 23:13:33
3020	Constanta	ROM	Constanta	342264	2007-12-06 21:18:27
3021	Cluj-Napoca	ROM	Cluj	332498	2002-09-07 23:09:44
3022	Galati	ROM	Galati	330276	2013-04-15 23:42:24
3023	Timisoara	ROM	Timis	324304	2014-05-12 05:33:27
3024	Brasov	ROM	Brasov	314225	2006-03-21 05:30:28
3025	Craiova	ROM	Dolj	313530	2010-11-02 16:28:34
3026	Ploiesti	ROM	Prahova	251348	2004-09-06 17:30:24
3027	Braila	ROM	Braila	233756	2007-01-23 13:38:37
3028	Oradea	ROM	Bihor	222239	2008-02-07 14:45:21
3029	Bacau	ROM	Bacau	209235	2010-02-17 19:38:51
3030	Pitesti	ROM	Arges	187170	2007-06-06 16:56:21
3031	Arad	ROM	Arad	184408	2010-11-06 06:48:17
3032	Sibiu	ROM	Sibiu	169611	2010-07-12 01:47:07
3033	Târgu Mures	ROM	Mures	165153	2009-07-01 23:06:12
3034	Baia Mare	ROM	Maramures	149665	2014-02-24 17:06:26
3035	Buzau	ROM	Buzau	148372	2003-05-07 11:09:54
3036	Satu Mare	ROM	Satu Mare	130059	2008-03-12 23:53:06
3037	Botosani	ROM	Botosani	128730	2007-09-20 11:33:48
3038	Piatra Neamt	ROM	Neamt	125070	2010-12-14 23:56:35
3039	Râmnicu Vâlcea	ROM	Vâlcea	119741	2001-09-21 23:45:19
3040	Suceava	ROM	Suceava	118549	2011-05-03 19:35:20
3041	Drobeta-Turnu Severin	ROM	Mehedinti	117865	2008-09-04 23:19:16
3042	Târgoviste	ROM	Dâmbovita	98980	2013-12-23 16:10:45
3043	Focsani	ROM	Vrancea	98979	2014-06-23 03:15:42
3044	Târgu Jiu	ROM	Gorj	98524	2006-11-26 09:31:59
3045	Tulcea	ROM	Tulcea	96278	2001-03-26 14:34:14
3046	Resita	ROM	Caras-Severin	93976	2007-07-13 16:09:40
3047	Kigali	RWA	Kigali	286000	2003-08-13 07:49:24
3048	Stockholm	SWE	Lisboa	750348	2014-09-09 13:27:06
3049	Gothenburg [Göteborg]	SWE	West Götanmaan län	466990	2003-11-23 01:06:36
3050	Malmö	SWE	Skåne län	259579	2011-09-04 23:14:50
3051	Uppsala	SWE	Uppsala län	189569	2007-01-17 09:15:11
3052	Linköping	SWE	East Götanmaan län	133168	2008-01-14 19:47:14
3053	Västerås	SWE	Västmanlands län	126328	2013-12-15 12:49:04
3054	Örebro	SWE	Örebros län	124207	2008-12-23 18:34:56
3055	Norrköping	SWE	East Götanmaan län	122199	2014-03-27 18:37:20
3056	Helsingborg	SWE	Skåne län	117737	2002-10-28 11:05:26
3057	Jönköping	SWE	Jönköpings län	117095	2005-12-11 07:32:53
3058	Umeå	SWE	Västerbottens län	104512	2001-03-04 16:10:49
3059	Lund	SWE	Skåne län	98948	2003-10-07 12:12:56
3060	Borås	SWE	West Götanmaan län	96883	2001-12-03 16:39:45
3061	Sundsvall	SWE	Västernorrlands län	93126	2001-02-20 01:48:06
3062	Gävle	SWE	Gävleborgs län	90742	2006-02-27 23:40:43
3063	Jamestown	SHN	Saint Helena	1500	2011-08-26 12:46:10
3064	Basseterre	KNA	St George Basseterre	11600	2009-03-18 20:23:24
3065	Castries	LCA	Castries	2301	2014-06-21 01:33:29
3066	Kingstown	VCT	St George	17100	2002-03-12 19:18:59
3067	Saint-Pierre	SPM	Saint-Pierre	5808	2014-02-20 02:15:12
3068	Berlin	DEU	Berliini	3386667	2007-06-14 23:04:44
3069	Hamburg	DEU	Hamburg	1704735	2011-05-11 23:25:38
3070	Munich [München]	DEU	Baijeri	1194560	2003-05-20 10:53:07
3071	Köln	DEU	Nordrhein-Westfalen	962507	2009-07-23 18:48:49
3072	Frankfurt am Main	DEU	Hessen	643821	2010-11-20 15:53:54
3073	Essen	DEU	Nordrhein-Westfalen	599515	2011-02-13 20:02:06
3074	Dortmund	DEU	Nordrhein-Westfalen	590213	2012-03-02 23:23:54
3075	Stuttgart	DEU	Baden-Württemberg	582443	2007-02-10 12:10:43
3076	Düsseldorf	DEU	Nordrhein-Westfalen	568855	2004-05-24 07:44:56
3077	Bremen	DEU	Bremen	540330	2013-07-04 23:52:06
3078	Duisburg	DEU	Nordrhein-Westfalen	519793	2012-03-23 14:15:19
3079	Hannover	DEU	Niedersachsen	514718	2008-01-09 06:34:42
3080	Leipzig	DEU	Saksi	489532	2011-03-06 11:10:43
3081	Nürnberg	DEU	Baijeri	486628	2008-09-02 22:44:22
3082	Dresden	DEU	Saksi	476668	2003-03-08 22:07:32
3083	Bochum	DEU	Nordrhein-Westfalen	392830	2011-11-14 13:42:23
3084	Wuppertal	DEU	Nordrhein-Westfalen	368993	2010-04-04 12:50:56
3085	Bielefeld	DEU	Nordrhein-Westfalen	321125	2014-01-14 22:40:09
3086	Mannheim	DEU	Baden-Württemberg	307730	2014-11-09 23:48:08
3087	Bonn	DEU	Nordrhein-Westfalen	301048	2004-03-03 11:18:54
3088	Gelsenkirchen	DEU	Nordrhein-Westfalen	281979	2011-06-01 22:06:25
3089	Karlsruhe	DEU	Baden-Württemberg	277204	2013-12-28 17:07:55
3090	Wiesbaden	DEU	Hessen	268716	2006-02-06 01:40:47
3091	Münster	DEU	Nordrhein-Westfalen	264670	2012-12-14 19:44:55
3092	Mönchengladbach	DEU	Nordrhein-Westfalen	263697	2012-04-18 09:52:12
3093	Chemnitz	DEU	Saksi	263222	2014-08-19 12:02:28
3094	Augsburg	DEU	Baijeri	254867	2003-03-14 22:59:09
3095	Halle/Saale	DEU	Anhalt Sachsen	254360	2005-08-04 16:49:19
3096	Braunschweig	DEU	Niedersachsen	246322	2009-09-15 22:31:53
3097	Aachen	DEU	Nordrhein-Westfalen	243825	2011-01-11 18:27:50
3098	Krefeld	DEU	Nordrhein-Westfalen	241769	2004-04-02 01:18:59
3099	Magdeburg	DEU	Anhalt Sachsen	235073	2004-01-08 23:06:48
3100	Kiel	DEU	Schleswig-Holstein	233795	2004-06-24 12:20:08
3101	Oberhausen	DEU	Nordrhein-Westfalen	222349	2008-08-05 23:23:35
3102	Lübeck	DEU	Schleswig-Holstein	213326	2002-06-22 08:31:34
3103	Hagen	DEU	Nordrhein-Westfalen	205201	2007-07-13 15:18:50
3104	Rostock	DEU	Mecklenburg-Vorpomme	203279	2014-03-21 19:34:20
3105	Freiburg im Breisgau	DEU	Baden-Württemberg	202455	2014-03-09 16:15:15
3106	Erfurt	DEU	Thüringen	201267	2010-04-01 15:02:58
3107	Kassel	DEU	Hessen	196211	2002-09-05 22:30:53
3108	Saarbrücken	DEU	Saarland	183836	2010-04-20 12:55:47
3109	Mainz	DEU	Rheinland-Pfalz	183134	2013-06-06 09:38:54
3110	Hamm	DEU	Nordrhein-Westfalen	181804	2008-06-06 10:47:43
3111	Herne	DEU	Nordrhein-Westfalen	175661	2013-02-13 10:48:15
3112	Mülheim an der Ruhr	DEU	Nordrhein-Westfalen	173895	2008-10-21 21:05:42
3113	Solingen	DEU	Nordrhein-Westfalen	165583	2005-12-04 15:47:41
3114	Osnabrück	DEU	Niedersachsen	164539	2005-07-27 14:17:55
3115	Ludwigshafen am Rhein	DEU	Rheinland-Pfalz	163771	2011-05-27 04:36:48
3116	Leverkusen	DEU	Nordrhein-Westfalen	160841	2003-09-04 13:46:11
3117	Oldenburg	DEU	Niedersachsen	154125	2013-11-08 21:19:56
3118	Neuss	DEU	Nordrhein-Westfalen	149702	2007-01-14 16:13:44
3119	Heidelberg	DEU	Baden-Württemberg	139672	2013-03-21 13:25:11
3120	Darmstadt	DEU	Hessen	137776	2001-01-11 10:59:40
3121	Paderborn	DEU	Nordrhein-Westfalen	137647	2004-10-27 02:42:36
3122	Potsdam	DEU	Brandenburg	128983	2007-12-07 21:12:51
3123	Würzburg	DEU	Baijeri	127350	2004-06-27 03:02:58
3124	Regensburg	DEU	Baijeri	125236	2014-12-12 14:28:46
3125	Recklinghausen	DEU	Nordrhein-Westfalen	125022	2012-11-24 07:06:32
3126	Göttingen	DEU	Niedersachsen	124775	2001-06-14 04:55:54
3127	Bremerhaven	DEU	Bremen	122735	2014-10-03 19:02:54
3128	Wolfsburg	DEU	Niedersachsen	121954	2001-03-04 13:20:42
3129	Bottrop	DEU	Nordrhein-Westfalen	121097	2001-04-02 07:17:04
3130	Remscheid	DEU	Nordrhein-Westfalen	120125	2004-06-28 19:38:55
3131	Heilbronn	DEU	Baden-Württemberg	119526	2011-10-08 17:36:44
3132	Pforzheim	DEU	Baden-Württemberg	117227	2012-02-17 04:09:41
3133	Offenbach am Main	DEU	Hessen	116627	2008-12-19 20:31:39
3134	Ulm	DEU	Baden-Württemberg	116103	2012-07-22 09:43:05
3135	Ingolstadt	DEU	Baijeri	114826	2002-03-17 12:33:13
3136	Gera	DEU	Thüringen	114718	2003-08-14 20:58:44
3137	Salzgitter	DEU	Niedersachsen	112934	2007-01-11 02:41:05
3138	Cottbus	DEU	Brandenburg	110894	2004-07-04 05:50:31
3139	Reutlingen	DEU	Baden-Württemberg	110343	2014-07-20 20:04:11
3140	Fürth	DEU	Baijeri	109771	2002-12-14 08:11:13
3141	Siegen	DEU	Nordrhein-Westfalen	109225	2005-03-23 15:36:03
3142	Koblenz	DEU	Rheinland-Pfalz	108003	2004-06-01 23:29:32
3143	Moers	DEU	Nordrhein-Westfalen	106837	2007-03-01 14:32:43
3144	Bergisch Gladbach	DEU	Nordrhein-Westfalen	106150	2013-03-16 23:12:36
3145	Zwickau	DEU	Saksi	104146	2012-02-07 08:17:34
3146	Hildesheim	DEU	Niedersachsen	104013	2008-09-12 09:45:43
3147	Witten	DEU	Nordrhein-Westfalen	103384	2004-10-07 13:42:29
3148	Schwerin	DEU	Mecklenburg-Vorpomme	102878	2012-08-22 20:11:42
3149	Erlangen	DEU	Baijeri	100750	2006-09-21 15:51:38
3150	Kaiserslautern	DEU	Rheinland-Pfalz	100025	2001-02-15 23:26:22
3151	Trier	DEU	Rheinland-Pfalz	99891	2005-02-16 07:10:29
3152	Jena	DEU	Thüringen	99779	2010-10-14 20:09:28
3153	Iserlohn	DEU	Nordrhein-Westfalen	99474	2013-03-25 14:05:33
3154	Gütersloh	DEU	Nordrhein-Westfalen	95028	2003-07-24 11:51:52
3155	Marl	DEU	Nordrhein-Westfalen	93735	2003-04-15 03:47:03
3156	Lünen	DEU	Nordrhein-Westfalen	92044	2002-06-02 17:20:33
3157	Düren	DEU	Nordrhein-Westfalen	91092	2007-05-05 13:34:07
3158	Ratingen	DEU	Nordrhein-Westfalen	90951	2009-07-15 13:37:57
3159	Velbert	DEU	Nordrhein-Westfalen	89881	2005-11-10 05:48:04
3160	Esslingen am Neckar	DEU	Baden-Württemberg	89667	2012-04-05 17:10:45
3161	Honiara	SLB	Honiara	50100	2001-01-16 09:13:53
3162	Lusaka	ZMB	Lusaka	1317000	2007-08-11 12:52:07
3163	Ndola	ZMB	Copperbelt	329200	2007-04-28 11:26:07
3164	Kitwe	ZMB	Copperbelt	288600	2011-07-02 13:23:08
3165	Kabwe	ZMB	Central	154300	2002-01-20 06:55:04
3166	Chingola	ZMB	Copperbelt	142400	2006-07-26 02:14:41
3167	Mufulira	ZMB	Copperbelt	123900	2009-03-14 07:56:04
3168	Luanshya	ZMB	Copperbelt	118100	2009-03-18 14:52:23
3169	Apia	WSM	Upolu	35900	2002-12-12 02:43:51
3170	Serravalle	SMR	Serravalle/Dogano	4802	2008-01-13 21:11:07
3171	San Marino	SMR	San Marino	2294	2009-11-03 02:53:22
3172	São Tomé	STP	Aqua Grande	49541	2014-06-10 15:54:50
3173	Riyadh	SAU	Riyadh	3324000	2014-04-05 17:23:30
3174	Jedda	SAU	Mekka	2046300	2009-04-11 15:32:07
3175	Mekka	SAU	Mekka	965700	2007-08-13 08:45:06
3176	Medina	SAU	Medina	608300	2009-02-16 17:23:57
3177	al-Dammam	SAU	al-Sharqiya	482300	2005-08-08 02:33:31
3178	al-Taif	SAU	Mekka	416100	2005-08-10 17:30:45
3179	Tabuk	SAU	Tabuk	292600	2014-09-08 01:45:57
3180	Burayda	SAU	al-Qasim	248600	2007-03-04 23:04:40
3181	al-Hufuf	SAU	al-Sharqiya	225800	2005-08-20 03:47:12
3182	al-Mubarraz	SAU	al-Sharqiya	219100	2012-05-06 17:01:28
3183	Khamis Mushayt	SAU	Asir	217900	2007-04-13 18:23:40
3184	Hail	SAU	Hail	176800	2004-07-17 07:58:26
3185	al-Kharj	SAU	Riad	152100	2005-01-06 16:12:34
3186	al-Khubar	SAU	al-Sharqiya	141700	2002-04-20 18:38:38
3187	Jubayl	SAU	al-Sharqiya	140800	2005-04-27 15:20:48
3188	Hafar al-Batin	SAU	al-Sharqiya	137800	2013-02-02 14:08:51
3189	al-Tuqba	SAU	al-Sharqiya	125700	2007-05-02 12:04:24
3190	Yanbu	SAU	Medina	119800	2012-06-16 05:54:54
3191	Abha	SAU	Asir	112300	2011-11-04 07:20:07
3192	Ara´ar	SAU	al-Khudud al-Samaliy	108100	2013-01-10 07:49:39
3193	al-Qatif	SAU	al-Sharqiya	98900	2013-01-15 04:52:28
3194	al-Hawiya	SAU	Mekka	93900	2004-01-21 03:22:51
3195	Unayza	SAU	Qasim	91100	2002-03-16 23:27:45
3196	Najran	SAU	Najran	91000	2008-12-12 22:46:56
3197	Pikine	SEN	Cap-Vert	855287	2011-03-05 23:10:55
3198	Dakar	SEN	Cap-Vert	785071	2003-08-20 11:49:01
3199	Thiès	SEN	Thiès	248000	2007-04-19 04:13:18
3200	Kaolack	SEN	Kaolack	199000	2006-11-26 08:31:30
3201	Ziguinchor	SEN	Ziguinchor	192000	2014-10-23 16:23:33
3202	Rufisque	SEN	Cap-Vert	150000	2007-05-21 03:26:03
3203	Saint-Louis	SEN	Saint-Louis	132400	2001-02-22 11:43:28
3204	Mbour	SEN	Thiès	109300	2004-09-19 05:34:34
3205	Diourbel	SEN	Diourbel	99400	2013-10-10 20:14:52
3206	Victoria	SYC	Mahé	41000	2012-11-26 16:25:05
3207	Freetown	SLE	Western	850000	2007-07-11 23:23:27
3208	Singapore	SGP		4017733	2002-07-10 19:51:42
3209	Bratislava	SVK	Bratislava	448292	2013-06-24 08:27:14
3210	Koice	SVK	Východné Slovensko	241874	2011-12-19 08:25:41
3211	Preov	SVK	Východné Slovensko	93977	2005-04-06 23:56:36
3212	Ljubljana	SVN	Osrednjeslovenska	270986	2009-07-03 16:38:08
3213	Maribor	SVN	Podravska	115532	2007-11-16 22:39:20
3214	Mogadishu	SOM	Banaadir	997000	2002-02-27 22:23:06
3215	Hargeysa	SOM	Woqooyi Galbeed	90000	2006-09-20 18:28:27
3216	Kismaayo	SOM	Jubbada Hoose	90000	2003-05-28 07:33:37
3217	Colombo	LKA	Western	645000	2005-08-07 04:50:28
3218	Dehiwala	LKA	Western	203000	2004-09-16 04:02:13
3219	Moratuwa	LKA	Western	190000	2001-07-13 02:04:36
3220	Jaffna	LKA	Northern	149000	2011-05-15 15:58:43
3221	Kandy	LKA	Central	140000	2014-04-10 03:53:30
3222	Sri Jayawardenepura Kotte	LKA	Western	118000	2013-05-28 06:45:34
3223	Negombo	LKA	Western	100000	2011-10-12 21:35:43
3224	Omdurman	SDN	Khartum	1271403	2012-10-18 20:07:34
3225	Khartum	SDN	Khartum	947483	2014-09-19 01:02:07
3226	Sharq al-Nil	SDN	Khartum	700887	2004-02-15 17:35:08
3227	Port Sudan	SDN	al-Bahr al-Ahmar	308195	2011-08-11 10:41:12
3228	Kassala	SDN	Kassala	234622	2004-07-07 11:01:01
3229	Obeid	SDN	Kurdufan al-Shamaliy	229425	2010-03-23 23:27:21
3230	Nyala	SDN	Darfur al-Janubiya	227183	2013-09-03 05:59:33
3231	Wad Madani	SDN	al-Jazira	211362	2013-06-08 23:32:14
3232	al-Qadarif	SDN	al-Qadarif	191164	2003-03-25 04:23:24
3233	Kusti	SDN	al-Bahr al-Abyad	173599	2007-06-14 19:48:29
3234	al-Fashir	SDN	Darfur al-Shamaliya	141884	2002-03-23 17:07:20
3235	Juba	SDN	Bahr al-Jabal	114980	2011-08-01 15:43:33
3236	Helsinki [Helsingfors]	FIN	Newmaa	555474	2011-09-17 08:34:49
3237	Espoo	FIN	Newmaa	213271	2012-04-12 06:23:59
3238	Tampere	FIN	Pirkanmaa	195468	2013-08-17 19:41:33
3239	Vantaa	FIN	Newmaa	178471	2007-02-10 12:12:46
3240	Turku [Åbo]	FIN	Varsinais-Suomi	172561	2002-04-23 17:52:29
3241	Oulu	FIN	Pohjois-Pohjanmaa	120753	2005-07-25 23:03:11
3242	Lahti	FIN	Päijät-Häme	96921	2011-07-05 06:29:26
3243	Paramaribo	SUR	Paramaribo	112000	2006-07-22 21:47:47
3244	Mbabane	SWZ	Hhohho	61000	2010-03-17 02:07:23
3245	Zürich	CHE	Zürich	336800	2004-06-09 18:05:29
3246	Geneve	CHE	Geneve	173500	2005-02-01 07:02:33
3247	Basel	CHE	Basel-Stadt	166700	2010-09-01 14:23:58
3248	Bern	CHE	Bern	122700	2009-12-12 13:06:40
3249	Lausanne	CHE	Vaud	114500	2002-12-16 12:56:29
3250	Damascus	SYR	Damascus	1347000	2009-10-24 14:56:20
3251	Aleppo	SYR	Aleppo	1261983	2013-01-21 12:06:31
3252	Hims	SYR	Hims	507404	2005-04-10 09:53:57
3253	Hama	SYR	Hama	343361	2008-04-16 01:13:52
3254	Latakia	SYR	Latakia	264563	2010-08-24 03:52:37
3255	al-Qamishliya	SYR	al-Hasaka	144286	2010-03-28 12:40:15
3256	Dayr al-Zawr	SYR	Dayr al-Zawr	140459	2011-06-05 11:30:58
3257	Jaramana	SYR	Damaskos	138469	2006-03-11 08:47:31
3258	Duma	SYR	Damaskos	131158	2011-02-18 07:26:25
3259	al-Raqqa	SYR	al-Raqqa	108020	2008-01-11 03:47:14
3260	Idlib	SYR	Idlib	91081	2010-07-26 21:23:13
3261	Dushanbe	TJK	Karotegin	524000	2011-09-21 23:16:52
3262	Khujand	TJK	Khujand	161500	2013-10-05 13:18:56
3263	Taipei	TWN	Taipei	2641312	2003-11-15 01:37:58
3264	Kaohsiung	TWN	Kaohsiung	1475505	2007-01-12 17:36:53
3265	Taichung	TWN	Taichung	940589	2003-05-11 07:56:04
3266	Tainan	TWN	Tainan	728060	2010-02-21 15:35:10
3267	Panchiao	TWN	Taipei	523850	2014-12-25 20:44:32
3268	Chungho	TWN	Taipei	392176	2002-10-23 17:58:56
3269	Keelung (Chilung)	TWN	Keelung	385201	2012-01-26 06:52:18
3270	Sanchung	TWN	Taipei	380084	2008-02-07 16:43:10
3271	Hsinchuang	TWN	Taipei	365048	2003-03-04 18:51:55
3272	Hsinchu	TWN	Hsinchu	361958	2010-09-23 14:11:33
3273	Chungli	TWN	Taoyuan	318649	2003-05-10 02:55:25
3274	Fengshan	TWN	Kaohsiung	318562	2011-07-16 11:32:04
3275	Taoyuan	TWN	Taoyuan	316438	2002-08-20 14:18:15
3276	Chiayi	TWN	Chiayi	265109	2011-02-05 16:58:38
3277	Hsintien	TWN	Taipei	263603	2001-11-26 22:12:39
3278	Changhwa	TWN	Changhwa	227715	2011-06-02 09:59:42
3279	Yungho	TWN	Taipei	227700	2003-08-01 13:15:15
3280	Tucheng	TWN	Taipei	224897	2006-07-08 06:29:05
3281	Pingtung	TWN	Pingtung	214727	2001-04-28 02:53:05
3282	Yungkang	TWN	Tainan	193005	2011-07-15 01:50:57
3283	Pingchen	TWN	Taoyuan	188344	2013-02-11 23:13:50
3284	Tali	TWN	Taichung	171940	2009-02-13 16:52:55
3285	Taiping	TWN		165524	2008-05-06 16:47:17
3286	Pate	TWN	Taoyuan	161700	2012-09-08 15:58:51
3287	Fengyuan	TWN	Taichung	161032	2006-06-04 05:05:37
3288	Luchou	TWN	Taipei	160516	2007-10-09 23:36:13
3289	Hsichuh	TWN	Taipei	154976	2011-11-17 10:10:17
3290	Shulin	TWN	Taipei	151260	2001-07-04 22:20:12
3291	Yuanlin	TWN	Changhwa	126402	2004-04-26 21:47:31
3292	Yangmei	TWN	Taoyuan	126323	2014-01-01 05:44:02
3293	Taliao	TWN		115897	2001-09-13 15:01:01
3294	Kueishan	TWN		112195	2009-12-18 13:13:02
3295	Tanshui	TWN	Taipei	111882	2003-04-10 21:20:55
3296	Taitung	TWN	Taitung	111039	2009-05-06 13:05:01
3297	Hualien	TWN	Hualien	108407	2001-09-07 13:20:32
3298	Nantou	TWN	Nantou	104723	2011-09-11 17:01:53
3299	Lungtan	TWN	Taipei	103088	2001-02-25 23:14:44
3300	Touliu	TWN	Yünlin	98900	2010-01-19 16:38:41
3301	Tsaotun	TWN	Nantou	96800	2006-08-20 20:01:23
3302	Kangshan	TWN	Kaohsiung	92200	2005-06-13 22:28:30
3303	Ilan	TWN	Ilan	92000	2010-07-04 01:49:04
3304	Miaoli	TWN	Miaoli	90000	2013-10-03 18:52:15
3305	Dar es Salaam	TZA	Dar es Salaam	1747000	2001-08-18 07:42:11
3306	Dodoma	TZA	Dodoma	189000	2001-06-04 15:18:28
3307	Mwanza	TZA	Mwanza	172300	2002-07-14 20:34:23
3308	Zanzibar	TZA	Zanzibar West	157634	2011-05-02 13:57:01
3309	Tanga	TZA	Tanga	137400	2006-10-28 18:18:39
3310	Mbeya	TZA	Mbeya	130800	2012-11-17 02:50:43
3311	Morogoro	TZA	Morogoro	117800	2012-01-14 12:03:22
3312	Arusha	TZA	Arusha	102500	2005-02-02 12:09:57
3313	Moshi	TZA	Kilimanjaro	96800	2001-10-16 19:01:27
3314	Tabora	TZA	Tabora	92800	2003-02-16 06:43:10
3315	København	DNK	København	495699	2013-05-08 16:50:03
3316	Århus	DNK	Århus	284846	2012-06-01 22:33:29
3317	Odense	DNK	Fyn	183912	2008-10-22 02:20:02
3318	Aalborg	DNK	Nordjylland	161161	2009-03-22 01:37:35
3319	Frederiksberg	DNK	Frederiksberg	90327	2005-09-28 07:07:42
3320	Bangkok	THA	Bangkok	6320174	2003-11-24 08:32:53
3321	Nonthaburi	THA	Nonthaburi	292100	2004-08-19 02:55:36
3322	Nakhon Ratchasima	THA	Nakhon Ratchasima	181400	2006-01-25 06:46:24
3323	Chiang Mai	THA	Chiang Mai	171100	2006-08-25 15:23:02
3324	Udon Thani	THA	Udon Thani	158100	2001-11-09 06:23:48
3325	Hat Yai	THA	Songkhla	148632	2014-12-24 10:03:54
3326	Khon Kaen	THA	Khon Kaen	126500	2008-08-10 04:48:03
3327	Pak Kret	THA	Nonthaburi	126055	2003-02-05 07:06:08
3328	Nakhon Sawan	THA	Nakhon Sawan	123800	2005-04-07 20:18:39
3329	Ubon Ratchathani	THA	Ubon Ratchathani	116300	2010-04-25 14:12:20
3330	Songkhla	THA	Songkhla	94900	2008-07-09 22:56:10
3331	Nakhon Pathom	THA	Nakhon Pathom	94100	2009-02-21 12:18:23
3332	Lomé	TGO	Maritime	375000	2002-06-27 04:02:44
3333	Fakaofo	TKL	Fakaofo	300	2007-05-13 20:26:58
3334	Nuku´alofa	TON	Tongatapu	22400	2010-08-09 19:32:19
3335	Chaguanas	TTO	Caroni	56601	2011-04-08 13:15:35
3336	Port-of-Spain	TTO	Port-of-Spain	43396	2008-08-21 13:55:09
3337	N´Djaména	TCD	Chari-Baguirmi	530965	2006-11-04 21:16:42
3338	Moundou	TCD	Logone Occidental	99500	2009-07-07 08:33:29
3339	Praha	CZE	Hlavní mesto Praha	1181126	2002-04-15 15:15:14
3340	Brno	CZE	Jizní Morava	381862	2011-03-13 16:44:21
3341	Ostrava	CZE	Severní Morava	320041	2009-01-20 19:46:48
3342	Plzen	CZE	Zapadní Cechy	166759	2012-05-15 23:05:38
3343	Olomouc	CZE	Severní Morava	102702	2001-01-12 14:58:33
3344	Liberec	CZE	Severní Cechy	99155	2007-05-19 23:08:36
3345	Ceské Budejovice	CZE	Jizní Cechy	98186	2010-10-16 23:50:39
3346	Hradec Králové	CZE	Východní Cechy	98080	2009-03-09 19:59:32
3347	Ústí nad Labem	CZE	Severní Cechy	95491	2011-04-03 02:46:55
3348	Pardubice	CZE	Východní Cechy	91309	2011-03-17 06:34:09
3349	Tunis	TUN	Tunis	690600	2011-01-23 12:41:49
3350	Sfax	TUN	Sfax	257800	2004-08-15 07:02:42
3351	Ariana	TUN	Ariana	197000	2013-02-19 18:41:52
3352	Ettadhamen	TUN	Ariana	178600	2008-06-20 01:59:09
3353	Sousse	TUN	Sousse	145900	2005-11-18 20:53:37
3354	Kairouan	TUN	Kairouan	113100	2004-01-24 21:19:36
3355	Biserta	TUN	Biserta	108900	2010-04-09 18:53:08
3356	Gabès	TUN	Gabès	106600	2003-06-21 14:48:20
3357	Istanbul	TUR	Istanbul	8787958	2001-01-10 20:27:07
3358	Ankara	TUR	Ankara	3038159	2003-07-15 17:16:41
3359	Izmir	TUR	Izmir	2130359	2012-09-10 15:21:39
3360	Adana	TUR	Adana	1131198	2004-02-23 10:09:16
3361	Bursa	TUR	Bursa	1095842	2003-06-21 03:02:28
3362	Gaziantep	TUR	Gaziantep	789056	2014-05-18 22:21:47
3363	Konya	TUR	Konya	628364	2007-01-17 06:17:33
3364	Mersin (Içel)	TUR	Içel	587212	2014-10-26 12:05:03
3365	Antalya	TUR	Antalya	564914	2003-04-21 12:14:56
3366	Diyarbakir	TUR	Diyarbakir	479884	2003-04-10 18:51:59
3367	Kayseri	TUR	Kayseri	475657	2013-01-17 19:27:03
3368	Eskisehir	TUR	Eskisehir	470781	2013-07-09 07:45:50
3369	Sanliurfa	TUR	Sanliurfa	405905	2012-11-08 07:45:33
3370	Samsun	TUR	Samsun	339871	2014-10-06 08:15:21
3371	Malatya	TUR	Malatya	330312	2001-06-24 10:46:42
3372	Gebze	TUR	Kocaeli	264170	2001-12-14 16:18:33
3373	Denizli	TUR	Denizli	253848	2010-01-20 03:45:59
3374	Sivas	TUR	Sivas	246642	2003-12-09 12:31:58
3375	Erzurum	TUR	Erzurum	246535	2013-08-15 18:50:16
3376	Tarsus	TUR	Adana	246206	2003-09-27 19:16:39
3377	Kahramanmaras	TUR	Kahramanmaras	245772	2003-01-14 11:19:11
3378	Elâzig	TUR	Elâzig	228815	2004-02-15 04:51:02
3379	Van	TUR	Van	219319	2005-03-20 11:52:38
3380	Sultanbeyli	TUR	Istanbul	211068	2006-12-07 03:30:46
3381	Izmit (Kocaeli)	TUR	Kocaeli	210068	2001-12-04 18:21:52
3382	Manisa	TUR	Manisa	207148	2009-11-18 10:05:31
3383	Batman	TUR	Batman	203793	2001-05-28 04:04:20
3384	Balikesir	TUR	Balikesir	196382	2002-05-02 18:38:33
3385	Sakarya (Adapazari)	TUR	Sakarya	190641	2012-04-13 04:54:22
3386	Iskenderun	TUR	Hatay	153022	2007-10-19 13:43:28
3387	Osmaniye	TUR	Osmaniye	146003	2014-04-18 10:14:44
3388	Çorum	TUR	Çorum	145495	2002-02-15 08:26:09
3389	Kütahya	TUR	Kütahya	144761	2012-10-26 16:42:01
3390	Hatay (Antakya)	TUR	Hatay	143982	2008-01-12 11:36:34
3391	Kirikkale	TUR	Kirikkale	142044	2013-05-28 01:48:59
3392	Adiyaman	TUR	Adiyaman	141529	2010-12-01 20:17:15
3393	Trabzon	TUR	Trabzon	138234	2006-03-20 17:59:19
3394	Ordu	TUR	Ordu	133642	2007-07-19 17:34:53
3395	Aydin	TUR	Aydin	128651	2010-08-13 14:38:23
3396	Usak	TUR	Usak	128162	2008-03-09 04:36:46
3397	Edirne	TUR	Edirne	123383	2010-01-27 15:29:05
3398	Çorlu	TUR	Tekirdag	123300	2002-03-13 13:53:51
3399	Isparta	TUR	Isparta	121911	2002-09-03 14:50:59
3400	Karabük	TUR	Karabük	118285	2002-06-05 02:42:30
3401	Kilis	TUR	Kilis	118245	2008-05-15 15:52:08
3402	Alanya	TUR	Antalya	117300	2009-01-02 03:20:46
3403	Kiziltepe	TUR	Mardin	112000	2008-05-19 06:40:53
3404	Zonguldak	TUR	Zonguldak	111542	2001-12-23 06:55:47
3405	Siirt	TUR	Siirt	107100	2007-11-09 22:14:55
3406	Viransehir	TUR	Sanliurfa	106400	2010-12-18 15:02:11
3407	Tekirdag	TUR	Tekirdag	106077	2006-12-09 13:17:37
3408	Karaman	TUR	Karaman	104200	2011-11-25 14:02:40
3409	Afyon	TUR	Afyon	103984	2004-11-07 05:26:05
3410	Aksaray	TUR	Aksaray	102681	2003-05-22 02:01:15
3411	Ceyhan	TUR	Adana	102412	2004-05-23 05:42:11
3412	Erzincan	TUR	Erzincan	102304	2013-12-24 21:52:17
3413	Bismil	TUR	Diyarbakir	101400	2008-03-26 16:37:30
3414	Nazilli	TUR	Aydin	99900	2010-03-15 19:51:05
3415	Tokat	TUR	Tokat	99500	2006-11-20 15:53:29
3416	Kars	TUR	Kars	93000	2014-09-02 05:29:56
3417	Inegöl	TUR	Bursa	90500	2002-10-23 05:02:47
3418	Bandirma	TUR	Balikesir	90200	2006-09-06 03:05:31
3419	Ashgabat	TKM	Ahal	540600	2002-02-12 10:02:57
3420	Chärjew	TKM	Lebap	189200	2010-11-19 04:49:24
3421	Dashhowuz	TKM	Dashhowuz	141800	2001-06-07 11:29:37
3422	Mary	TKM	Mary	101000	2002-07-24 16:41:07
3423	Cockburn Town	TCA	Grand Turk	4800	2002-01-16 04:06:10
3424	Funafuti	TUV	Funafuti	4600	2001-10-24 23:33:33
3425	Kampala	UGA	Central	890800	2010-04-04 20:54:01
3426	Kyiv	UKR	Kiova	2624000	2010-07-25 21:05:12
3427	Harkova [Harkiv]	UKR	Harkova	1500000	2013-02-28 03:49:16
3428	Dnipropetrovsk	UKR	Dnipropetrovsk	1103000	2011-06-11 16:22:33
3429	Donetsk	UKR	Donetsk	1050000	2011-04-19 02:36:04
3430	Odesa	UKR	Odesa	1011000	2013-06-17 03:13:09
3431	Zaporizzja	UKR	Zaporizzja	848000	2002-05-25 08:01:44
3432	Lviv	UKR	Lviv	788000	2011-04-05 23:10:06
3433	Kryvyi Rig	UKR	Dnipropetrovsk	703000	2009-10-14 21:59:37
3434	Mykolajiv	UKR	Mykolajiv	508000	2004-08-14 15:17:06
3435	Mariupol	UKR	Donetsk	490000	2007-07-03 14:02:48
3436	Lugansk	UKR	Lugansk	469000	2011-07-07 03:21:34
3437	Vinnytsja	UKR	Vinnytsja	391000	2012-10-09 02:49:29
3438	Makijivka	UKR	Donetsk	384000	2005-07-02 07:28:23
3439	Herson	UKR	Herson	353000	2004-12-20 02:16:20
3440	Sevastopol	UKR	Krim	348000	2012-01-08 04:51:21
3441	Simferopol	UKR	Krim	339000	2011-05-20 19:02:39
3442	Pultava [Poltava]	UKR	Pultava	313000	2011-12-08 22:20:28
3443	Ternigiv	UKR	Ternigiv	313000	2001-03-12 17:14:39
3444	Terkasy	UKR	Terkasy	309000	2002-06-11 22:42:45
3445	Gorlivka	UKR	Donetsk	299000	2008-07-02 21:23:47
3446	Zytomyr	UKR	Zytomyr	297000	2008-02-13 23:45:45
3447	Sumy	UKR	Sumy	294000	2001-01-05 17:29:39
3448	Dniprodzerzynsk	UKR	Dnipropetrovsk	270000	2004-10-01 18:59:43
3449	Kirovograd	UKR	Kirovograd	265000	2010-05-22 12:42:30
3450	Hmelnytskyi	UKR	Hmelnytskyi	262000	2012-01-09 08:43:18
3451	Ternivtsi	UKR	Ternivtsi	259000	2013-02-04 01:10:09
3452	Rivne	UKR	Rivne	245000	2010-04-09 15:14:11
3453	Krementuk	UKR	Pultava	239000	2009-10-21 02:23:08
3454	Ivano-Frankivsk	UKR	Ivano-Frankivsk	237000	2002-02-26 06:37:29
3455	Ternopil	UKR	Ternopil	236000	2007-06-28 19:56:15
3456	Lutsk	UKR	Volynia	217000	2008-02-20 22:18:48
3457	Bila Tserkva	UKR	Kiova	215000	2010-01-03 19:11:33
3458	Kramatorsk	UKR	Donetsk	186000	2014-08-10 13:13:14
3459	Melitopol	UKR	Zaporizzja	169000	2011-09-06 09:23:58
3460	Kert	UKR	Krim	162000	2003-03-03 09:58:02
3461	Nikopol	UKR	Dnipropetrovsk	149000	2005-08-11 04:58:46
3462	Berdjansk	UKR	Zaporizzja	130000	2006-08-18 10:27:06
3463	Pavlograd	UKR	Dnipropetrovsk	127000	2013-07-21 05:02:17
3464	Sjeverodonetsk	UKR	Lugansk	127000	2007-09-26 20:15:42
3465	Slovjansk	UKR	Donetsk	127000	2008-02-15 17:18:33
3466	Uzgorod	UKR	Taka-Karpatia	127000	2006-10-14 13:35:22
3467	Altevsk	UKR	Lugansk	119000	2010-04-12 06:05:43
3468	Lysytansk	UKR	Lugansk	116000	2004-06-08 20:42:54
3469	Jevpatorija	UKR	Krim	112000	2013-11-14 05:37:58
3470	Kamjanets-Podilskyi	UKR	Hmelnytskyi	109000	2011-06-16 05:08:51
3471	Jenakijeve	UKR	Donetsk	105000	2008-02-02 18:47:42
3472	Krasnyi Lut	UKR	Lugansk	101000	2006-06-26 16:51:13
3473	Stahanov	UKR	Lugansk	101000	2002-07-12 01:32:08
3474	Oleksandrija	UKR	Kirovograd	99000	2003-03-03 13:20:49
3475	Konotop	UKR	Sumy	96000	2004-11-07 13:29:30
3476	Kostjantynivka	UKR	Donetsk	95000	2009-08-10 23:58:16
3477	Berdytiv	UKR	Zytomyr	90000	2008-02-23 17:43:28
3478	Izmajil	UKR	Odesa	90000	2010-10-08 18:14:37
3479	ostka	UKR	Sumy	90000	2011-09-14 04:48:18
3480	Uman	UKR	Terkasy	90000	2002-07-28 08:04:53
3481	Brovary	UKR	Kiova	89000	2002-04-14 16:27:12
3482	Mukateve	UKR	Taka-Karpatia	89000	2009-09-04 23:47:22
3483	Budapest	HUN	Budapest	1811552	2003-03-10 05:30:24
3484	Debrecen	HUN	Hajdú-Bihar	203648	2002-07-22 16:29:01
3485	Miskolc	HUN	Borsod-Abaúj-Zemplén	172357	2005-04-27 21:56:30
3486	Szeged	HUN	Csongrád	158158	2001-01-12 14:42:48
3487	Pécs	HUN	Baranya	157332	2005-01-03 18:32:27
3488	Györ	HUN	Györ-Moson-Sopron	127119	2014-10-23 19:03:07
3489	Nyiregyháza	HUN	Szabolcs-Szatmár-Ber	112419	2009-04-02 12:58:14
3490	Kecskemét	HUN	Bács-Kiskun	105606	2003-08-05 16:49:04
3491	Székesfehérvár	HUN	Fejér	105119	2014-11-15 07:20:30
3492	Montevideo	URY	Montevideo	1236000	2007-05-08 17:55:27
3493	Nouméa	NCL		76293	2013-10-01 16:48:01
3494	Auckland	NZL	Auckland	381800	2012-08-21 21:23:54
3495	Christchurch	NZL	Canterbury	324200	2012-05-26 02:32:25
3496	Manukau	NZL	Auckland	281800	2005-06-06 05:38:17
3497	North Shore	NZL	Auckland	187700	2008-09-27 22:14:37
3498	Waitakere	NZL	Auckland	170600	2014-11-02 15:42:27
3499	Wellington	NZL	Wellington	166700	2004-12-25 21:46:49
3500	Dunedin	NZL	Dunedin	119600	2006-11-10 03:52:16
3501	Hamilton	NZL	Hamilton	117100	2004-02-11 06:48:37
3502	Lower Hutt	NZL	Wellington	98100	2002-08-07 01:05:23
3503	Toskent	UZB	Toskent Shahri	2117500	2001-06-24 23:56:52
3504	Namangan	UZB	Namangan	370500	2006-02-20 12:09:20
3505	Samarkand	UZB	Samarkand	361800	2001-06-01 21:14:07
3506	Andijon	UZB	Andijon	318600	2014-01-15 22:58:22
3507	Buhoro	UZB	Buhoro	237100	2011-02-20 10:33:57
3508	Karsi	UZB	Qashqadaryo	194100	2005-11-23 11:49:54
3509	Nukus	UZB	Karakalpakistan	194100	2013-03-08 23:51:06
3510	Kükon	UZB	Fargona	190100	2005-04-21 19:28:37
3511	Fargona	UZB	Fargona	180500	2010-01-07 06:52:59
3512	Circik	UZB	Toskent	146400	2010-10-21 15:59:57
3513	Margilon	UZB	Fargona	140800	2013-11-23 11:26:12
3514	Ürgenc	UZB	Khorazm	138900	2014-01-10 19:07:25
3515	Angren	UZB	Toskent	128000	2004-11-01 11:25:36
3516	Cizah	UZB	Cizah	124800	2002-04-04 23:01:25
3517	Navoi	UZB	Navoi	116300	2005-05-18 21:35:30
3518	Olmalik	UZB	Toskent	114900	2013-04-12 16:59:57
3519	Termiz	UZB	Surkhondaryo	109500	2009-03-02 04:31:33
3520	Minsk	BLR	Horad Minsk	1674000	2011-07-06 19:34:44
3521	Gomel	BLR	Gomel	475000	2012-03-19 12:49:11
3522	Mogiljov	BLR	Mogiljov	356000	2006-02-11 20:54:01
3523	Vitebsk	BLR	Vitebsk	340000	2005-10-18 12:19:12
3524	Grodno	BLR	Grodno	302000	2005-04-17 01:57:56
3525	Brest	BLR	Brest	286000	2013-07-25 05:55:37
3526	Bobruisk	BLR	Mogiljov	221000	2003-03-06 16:18:08
3527	Baranoviti	BLR	Brest	167000	2004-06-11 11:40:02
3528	Borisov	BLR	Minsk	151000	2012-02-04 15:27:07
3529	Pinsk	BLR	Brest	130000	2010-08-05 09:29:22
3530	Ora	BLR	Vitebsk	124000	2007-07-07 09:45:10
3531	Mozyr	BLR	Gomel	110000	2008-09-08 07:33:50
3532	Novopolotsk	BLR	Vitebsk	106000	2006-11-17 05:54:38
3533	Lida	BLR	Grodno	101000	2012-09-21 08:28:01
3534	Soligorsk	BLR	Minsk	101000	2009-04-08 12:45:09
3535	Molodetno	BLR	Minsk	97000	2010-02-15 23:04:43
3536	Mata-Utu	WLF	Wallis	1137	2012-12-02 17:37:01
3537	Port-Vila	VUT	Shefa	33700	2003-11-16 17:53:05
3538	Città del Vaticano	VAT		455	2014-01-26 23:17:49
3539	Caracas	VEN	Distrito Federal	1975294	2007-01-24 23:49:39
3540	Maracaíbo	VEN	Zulia	1304776	2012-04-08 09:37:11
3541	Barquisimeto	VEN	Lara	877239	2012-07-17 15:52:34
3542	Valencia	VEN	Carabobo	794246	2009-12-22 17:12:56
3543	Ciudad Guayana	VEN	Bolívar	663713	2011-05-09 16:16:33
3544	Petare	VEN	Miranda	488868	2006-01-08 17:41:29
3545	Maracay	VEN	Aragua	444443	2012-03-14 08:30:43
3546	Barcelona	VEN	Anzoátegui	322267	2009-08-06 16:49:31
3547	Maturín	VEN	Monagas	319726	2002-05-15 18:20:49
3548	San Cristóbal	VEN	Táchira	319373	2005-03-17 18:02:52
3549	Ciudad Bolívar	VEN	Bolívar	301107	2004-02-08 06:21:48
3550	Cumaná	VEN	Sucre	293105	2005-07-21 12:45:34
3551	Mérida	VEN	Mérida	224887	2011-07-25 19:56:26
3552	Cabimas	VEN	Zulia	221329	2004-07-27 17:27:47
3553	Barinas	VEN	Barinas	217831	2002-07-10 10:14:38
3554	Turmero	VEN	Aragua	217499	2013-12-21 12:43:58
3555	Baruta	VEN	Miranda	207290	2002-03-04 09:13:25
3556	Puerto Cabello	VEN	Carabobo	187722	2001-08-18 15:56:29
3557	Santa Ana de Coro	VEN	Falcón	185766	2004-09-24 23:12:07
3558	Los Teques	VEN	Miranda	178784	2003-10-03 11:30:34
3559	Punto Fijo	VEN	Falcón	167215	2011-12-21 20:26:56
3560	Guarenas	VEN	Miranda	165889	2003-07-03 06:53:09
3561	Acarigua	VEN	Portuguesa	158954	2010-02-13 08:19:20
3562	Puerto La Cruz	VEN	Anzoátegui	155700	2004-09-16 14:40:05
3563	Ciudad Losada	VEN		134501	2010-11-02 10:13:54
3564	Guacara	VEN	Carabobo	131334	2006-08-11 07:33:48
3565	Valera	VEN	Trujillo	130281	2013-09-21 04:08:31
3566	Guanare	VEN	Portuguesa	125621	2002-06-12 13:23:46
3567	Carúpano	VEN	Sucre	119639	2012-07-02 06:23:27
3568	Catia La Mar	VEN	Distrito Federal	117012	2008-06-18 02:27:32
3569	El Tigre	VEN	Anzoátegui	116256	2001-01-23 11:20:49
3570	Guatire	VEN	Miranda	109121	2012-06-02 21:08:25
3571	Calabozo	VEN	Guárico	107146	2011-03-10 09:51:01
3572	Pozuelos	VEN	Anzoátegui	105690	2008-04-28 08:15:33
3573	Ciudad Ojeda	VEN	Zulia	99354	2010-04-08 11:36:40
3574	Ocumare del Tuy	VEN	Miranda	97168	2005-11-05 06:45:30
3575	Valle de la Pascua	VEN	Guárico	95927	2009-04-25 18:44:29
3576	Araure	VEN	Portuguesa	94269	2004-08-26 17:25:52
3577	San Fernando de Apure	VEN	Apure	93809	2013-07-10 18:20:13
3578	San Felipe	VEN	Yaracuy	90940	2001-04-07 22:33:23
3579	El Limón	VEN	Aragua	90000	2005-06-11 11:32:21
3580	Moscow	RUS	Moscow (City)	8389200	2001-10-28 04:06:08
3581	St Petersburg	RUS	Pietari	4694000	2001-09-26 03:50:33
3582	Novosibirsk	RUS	Novosibirsk	1398800	2007-02-04 21:09:46
3583	Nizni Novgorod	RUS	Nizni Novgorod	1357000	2005-08-22 15:35:52
3584	Jekaterinburg	RUS	Sverdlovsk	1266300	2003-11-01 12:42:09
3585	Samara	RUS	Samara	1156100	2002-02-10 19:06:02
3586	Omsk	RUS	Omsk	1148900	2003-08-11 11:52:02
3587	Kazan	RUS	Tatarstan	1101000	2003-01-18 05:23:20
3588	Ufa	RUS	Bakortostan	1091200	2007-04-09 18:06:38
3589	Teljabinsk	RUS	Teljabinsk	1083200	2007-04-01 01:23:17
3590	Rostov-na-Donu	RUS	Rostov-na-Donu	1012700	2008-04-09 16:51:58
3591	Perm	RUS	Perm	1009700	2009-06-02 19:23:33
3592	Volgograd	RUS	Volgograd	993400	2006-05-18 10:50:33
3593	Voronez	RUS	Voronez	907700	2014-04-13 22:54:42
3594	Krasnojarsk	RUS	Krasnojarsk	875500	2014-11-06 10:38:49
3595	Saratov	RUS	Saratov	874000	2011-05-21 03:43:40
3596	Toljatti	RUS	Samara	722900	2010-03-04 16:25:02
3597	Uljanovsk	RUS	Uljanovsk	667400	2006-10-09 21:27:50
3598	Izevsk	RUS	Udmurtia	652800	2007-05-28 02:39:41
3599	Krasnodar	RUS	Krasnodar	639000	2010-09-14 05:56:02
3600	Jaroslavl	RUS	Jaroslavl	616700	2003-12-12 18:22:19
3601	Habarovsk	RUS	Habarovsk	609400	2009-02-21 05:33:49
3602	Vladivostok	RUS	Primorje	606200	2001-11-17 12:41:16
3603	Irkutsk	RUS	Irkutsk	593700	2014-12-01 04:13:10
3604	Barnaul	RUS	Altai	580100	2009-04-12 16:48:29
3605	Novokuznetsk	RUS	Kemerovo	561600	2011-11-10 14:30:33
3606	Penza	RUS	Penza	532200	2004-03-14 06:19:57
3607	Rjazan	RUS	Rjazan	529900	2011-03-28 05:39:21
3608	Orenburg	RUS	Orenburg	523600	2013-11-21 09:47:32
3609	Lipetsk	RUS	Lipetsk	521000	2005-08-09 16:50:12
3610	Nabereznyje Telny	RUS	Tatarstan	514700	2011-01-07 20:33:33
3611	Tula	RUS	Tula	506100	2014-11-02 12:21:11
3612	Tjumen	RUS	Tjumen	503400	2009-02-10 01:17:45
3613	Kemerovo	RUS	Kemerovo	492700	2004-11-03 08:05:44
3614	Astrahan	RUS	Astrahan	486100	2006-01-01 02:13:24
3615	Tomsk	RUS	Tomsk	482100	2009-01-09 23:16:52
3616	Kirov	RUS	Kirov	466200	2003-04-24 07:20:50
3617	Ivanovo	RUS	Ivanovo	459200	2011-09-13 06:49:41
3618	Teboksary	RUS	Tuvassia	459200	2010-09-03 18:29:18
3619	Brjansk	RUS	Brjansk	457400	2002-01-10 11:11:05
3620	Tver	RUS	Tver	454900	2010-02-02 13:10:13
3621	Kursk	RUS	Kursk	443500	2011-05-05 04:53:27
3622	Magnitogorsk	RUS	Teljabinsk	427900	2008-11-10 20:01:25
3623	Kaliningrad	RUS	Kaliningrad	424400	2014-01-19 01:45:35
3624	Nizni Tagil	RUS	Sverdlovsk	390900	2003-04-25 16:20:47
3625	Murmansk	RUS	Murmansk	376300	2007-09-16 09:53:17
3626	Ulan-Ude	RUS	Burjatia	370400	2009-01-27 13:31:43
3627	Kurgan	RUS	Kurgan	364700	2002-09-21 13:44:14
3628	Arkangeli	RUS	Arkangeli	361800	2010-08-08 04:50:24
3629	Soti	RUS	Krasnodar	358600	2009-01-06 23:11:35
3630	Smolensk	RUS	Smolensk	353400	2012-09-09 13:42:27
3631	Orjol	RUS	Orjol	344500	2003-07-22 22:52:56
3632	Stavropol	RUS	Stavropol	343300	2007-07-07 06:14:01
3633	Belgorod	RUS	Belgorod	342000	2003-10-25 20:48:42
3634	Kaluga	RUS	Kaluga	339300	2010-12-17 19:29:32
3635	Vladimir	RUS	Vladimir	337100	2008-12-27 04:26:21
3636	Mahatkala	RUS	Dagestan	332800	2005-11-14 05:14:14
3637	Terepovets	RUS	Vologda	324400	2002-09-22 16:47:48
3638	Saransk	RUS	Mordva	314800	2009-08-28 03:58:39
3639	Tambov	RUS	Tambov	312000	2004-04-09 14:03:50
3640	Vladikavkaz	RUS	North Ossetia-Alania	310100	2014-01-02 23:47:08
3641	Tita	RUS	Tita	309900	2011-08-02 20:23:39
3642	Vologda	RUS	Vologda	302500	2014-09-12 08:41:50
3643	Veliki Novgorod	RUS	Novgorod	299500	2012-06-08 03:45:17
3644	Komsomolsk-na-Amure	RUS	Habarovsk	291600	2009-11-25 22:14:25
3645	Kostroma	RUS	Kostroma	288100	2006-07-02 19:14:13
3646	Volzski	RUS	Volgograd	286900	2006-05-18 03:17:59
3647	Taganrog	RUS	Rostov-na-Donu	284400	2014-01-23 03:40:33
3648	Petroskoi	RUS	Karjala	282100	2012-04-28 12:33:09
3649	Bratsk	RUS	Irkutsk	277600	2011-07-10 15:44:17
3650	Dzerzinsk	RUS	Nizni Novgorod	277100	2014-03-08 23:54:14
3651	Surgut	RUS	Hanti-Mansia	274900	2004-10-08 21:39:31
3652	Orsk	RUS	Orenburg	273900	2004-01-14 06:33:32
3653	Sterlitamak	RUS	Bakortostan	265200	2008-11-05 03:28:30
3654	Angarsk	RUS	Irkutsk	264700	2009-05-17 04:04:05
3655	Jokar-Ola	RUS	Marinmaa	249200	2013-10-07 14:44:31
3656	Rybinsk	RUS	Jaroslavl	239600	2002-10-28 01:51:33
3657	Prokopjevsk	RUS	Kemerovo	237300	2003-03-06 22:38:59
3658	Niznevartovsk	RUS	Hanti-Mansia	233900	2013-10-20 10:47:12
3659	Naltik	RUS	Kabardi-Balkaria	233400	2012-08-20 02:04:24
3660	Syktyvkar	RUS	Komi	229700	2014-05-03 02:20:16
3661	Severodvinsk	RUS	Arkangeli	229300	2006-11-26 14:58:04
3662	Bijsk	RUS	Altai	225000	2013-01-12 10:36:12
3663	Niznekamsk	RUS	Tatarstan	223400	2012-08-21 15:26:32
3664	Blagovetensk	RUS	Amur	222000	2005-12-11 05:57:56
3665	ahty	RUS	Rostov-na-Donu	221800	2014-08-19 06:28:53
3666	Staryi Oskol	RUS	Belgorod	213800	2010-06-04 03:38:55
3667	Zelenograd	RUS	Moscow (City)	207100	2014-04-15 13:59:46
3668	Balakovo	RUS	Saratov	206000	2005-12-18 23:07:02
3669	Novorossijsk	RUS	Krasnodar	203300	2003-06-01 19:34:59
3670	Pihkova	RUS	Pihkova	201500	2002-05-04 15:18:15
3671	Zlatoust	RUS	Teljabinsk	196900	2006-10-20 11:53:22
3672	Jakutsk	RUS	Saha (Jakutia)	195400	2007-02-03 06:38:47
3673	Podolsk	RUS	Moskova	194300	2008-12-26 23:33:26
3674	Petropavlovsk-Kamtatski	RUS	Kamtatka	194100	2003-05-12 05:33:03
3675	Kamensk-Uralski	RUS	Sverdlovsk	190600	2004-11-28 01:26:40
3676	Engels	RUS	Saratov	189000	2013-12-26 17:59:09
3677	Syzran	RUS	Samara	186900	2012-03-16 04:06:31
3678	Grozny	RUS	Tetenia	186000	2003-10-15 23:16:13
3679	Novoterkassk	RUS	Rostov-na-Donu	184400	2005-06-27 06:03:12
3680	Berezniki	RUS	Perm	181900	2002-09-15 12:22:26
3681	Juzno-Sahalinsk	RUS	Sahalin	179200	2009-02-09 16:40:33
3682	Volgodonsk	RUS	Rostov-na-Donu	178200	2010-01-25 08:53:50
3683	Abakan	RUS	Hakassia	169200	2001-10-18 23:03:31
3684	Maikop	RUS	Adygea	167300	2014-12-08 09:49:24
3685	Miass	RUS	Teljabinsk	166200	2012-09-18 16:33:52
3686	Armavir	RUS	Krasnodar	164900	2006-09-04 11:36:33
3687	Ljubertsy	RUS	Moskova	163900	2004-09-18 08:35:52
3688	Rubtsovsk	RUS	Altai	162600	2013-03-15 08:45:24
3689	Kovrov	RUS	Vladimir	159900	2003-05-05 04:57:25
3690	Nahodka	RUS	Primorje	157700	2014-04-05 11:13:57
3691	Ussurijsk	RUS	Primorje	157300	2003-04-02 08:48:46
3692	Salavat	RUS	Bakortostan	156800	2009-08-10 09:33:38
3693	Mytiti	RUS	Moskova	155700	2006-05-21 04:01:36
3694	Kolomna	RUS	Moskova	150700	2010-02-11 18:42:42
3695	Elektrostal	RUS	Moskova	147000	2003-10-23 12:03:07
3696	Murom	RUS	Vladimir	142400	2002-01-10 03:08:29
3697	Kolpino	RUS	Pietari	141200	2001-07-02 22:50:19
3698	Norilsk	RUS	Krasnojarsk	140800	2003-06-18 23:40:54
3699	Almetjevsk	RUS	Tatarstan	140700	2008-04-21 18:50:44
3700	Novomoskovsk	RUS	Tula	138100	2012-02-12 11:22:35
3701	Dimitrovgrad	RUS	Uljanovsk	137000	2007-07-25 13:15:11
3702	Pervouralsk	RUS	Sverdlovsk	136100	2009-04-11 15:56:47
3703	Himki	RUS	Moskova	133700	2003-09-26 06:23:29
3704	Balaiha	RUS	Moskova	132900	2014-07-26 21:25:02
3705	Nevinnomyssk	RUS	Stavropol	132600	2009-01-10 19:33:05
3706	Pjatigorsk	RUS	Stavropol	132500	2007-03-27 21:18:59
3707	Korolev	RUS	Moskova	132400	2012-02-24 20:05:57
3708	Serpuhov	RUS	Moskova	132000	2009-06-06 19:09:42
3709	Odintsovo	RUS	Moskova	127400	2013-01-02 22:52:57
3710	Orehovo-Zujevo	RUS	Moskova	124900	2001-04-05 15:22:32
3711	Kamyin	RUS	Volgograd	124600	2013-02-03 20:04:21
3712	Novoteboksarsk	RUS	Tuvassia	123400	2011-06-07 01:52:44
3713	Terkessk	RUS	Karatai-Terkessia	121700	2003-05-01 23:53:46
3714	Atinsk	RUS	Krasnojarsk	121600	2002-05-08 08:13:07
3715	Magadan	RUS	Magadan	121000	2014-05-07 17:37:43
3716	Miturinsk	RUS	Tambov	120700	2011-05-16 08:52:56
3717	Kislovodsk	RUS	Stavropol	120400	2001-11-16 06:44:05
3718	Jelets	RUS	Lipetsk	119400	2010-03-22 23:23:23
3719	Seversk	RUS	Tomsk	118600	2011-06-25 09:52:40
3720	Noginsk	RUS	Moskova	117200	2004-02-04 22:53:52
3721	Velikije Luki	RUS	Pihkova	116300	2007-09-25 21:13:38
3722	Novokuibyevsk	RUS	Samara	116200	2013-09-16 11:19:07
3723	Neftekamsk	RUS	Bakortostan	115700	2012-05-03 09:11:57
3724	Leninsk-Kuznetski	RUS	Kemerovo	113800	2005-12-05 05:35:49
3725	Oktjabrski	RUS	Bakortostan	111500	2014-01-09 06:03:06
3726	Sergijev Posad	RUS	Moskova	111100	2013-06-08 19:41:47
3727	Arzamas	RUS	Nizni Novgorod	110700	2009-11-03 10:40:14
3728	Kiseljovsk	RUS	Kemerovo	110000	2009-09-22 13:35:13
3729	Novotroitsk	RUS	Orenburg	109600	2013-06-12 17:27:22
3730	Obninsk	RUS	Kaluga	108300	2001-01-20 08:39:19
3731	Kansk	RUS	Krasnojarsk	107400	2008-08-05 18:31:30
3732	Glazov	RUS	Udmurtia	106300	2012-05-24 04:04:46
3733	Solikamsk	RUS	Perm	106000	2012-08-14 19:37:28
3734	Sarapul	RUS	Udmurtia	105700	2010-12-01 08:36:16
3735	Ust-Ilimsk	RUS	Irkutsk	105200	2006-04-17 23:46:34
3736	tolkovo	RUS	Moskova	104900	2001-12-15 21:08:06
3737	Mezduretensk	RUS	Kemerovo	104400	2002-06-26 15:33:26
3738	Usolje-Sibirskoje	RUS	Irkutsk	103500	2005-12-06 08:12:22
3739	Elista	RUS	Kalmykia	103300	2007-08-04 17:56:27
3740	Novoahtinsk	RUS	Rostov-na-Donu	101900	2003-02-25 18:30:40
3741	Votkinsk	RUS	Udmurtia	101700	2001-01-13 05:29:13
3742	Kyzyl	RUS	Tyva	101100	2008-11-14 02:21:20
3743	Serov	RUS	Sverdlovsk	100400	2004-03-24 22:59:27
3744	Zelenodolsk	RUS	Tatarstan	100200	2001-07-28 17:17:17
3745	Zeleznodoroznyi	RUS	Moskova	100100	2007-07-14 04:18:50
3746	Kinema	RUS	Ivanovo	100000	2007-04-27 16:39:37
3747	Kuznetsk	RUS	Penza	98200	2003-09-09 15:55:35
3748	Uhta	RUS	Komi	98000	2011-09-21 01:37:53
3749	Jessentuki	RUS	Stavropol	97900	2010-06-03 19:23:20
3750	Tobolsk	RUS	Tjumen	97600	2013-10-14 19:12:33
3751	Neftejugansk	RUS	Hanti-Mansia	97400	2005-06-19 05:15:51
3752	Bataisk	RUS	Rostov-na-Donu	97300	2007-01-09 04:04:45
3753	Nojabrsk	RUS	Yamalin Nenetsia	97300	2012-05-20 23:07:34
3754	Balaov	RUS	Saratov	97100	2011-08-04 07:59:29
3755	Zeleznogorsk	RUS	Kursk	96900	2006-02-28 08:01:47
3756	Zukovski	RUS	Moskova	96500	2012-12-12 07:44:55
3757	Anzero-Sudzensk	RUS	Kemerovo	96100	2006-03-11 06:11:09
3758	Bugulma	RUS	Tatarstan	94100	2012-02-27 05:50:04
3759	Zeleznogorsk	RUS	Krasnojarsk	94000	2010-04-12 03:59:25
3760	Novouralsk	RUS	Sverdlovsk	93300	2007-02-13 01:04:21
3761	Pukin	RUS	Pietari	92900	2005-05-09 02:35:02
3762	Vorkuta	RUS	Komi	92600	2001-12-02 14:47:42
3763	Derbent	RUS	Dagestan	92300	2014-01-20 15:23:25
3764	Kirovo-Tepetsk	RUS	Kirov	91600	2006-08-22 07:54:33
3765	Krasnogorsk	RUS	Moskova	91000	2002-05-09 02:56:37
3766	Klin	RUS	Moskova	90000	2002-05-08 13:13:05
3767	Taikovski	RUS	Perm	90000	2007-02-25 16:09:03
3768	Novyi Urengoi	RUS	Yamalin Nenetsia	89800	2003-03-01 07:47:28
3769	Ho Chi Minh City	VNM	Ho Chi Minh City	3980000	2009-03-10 08:52:17
3770	Hanoi	VNM	Hanoi	1410000	2007-04-02 14:39:02
3771	Haiphong	VNM	Haiphong	783133	2011-10-22 15:02:44
3772	Da Nang	VNM	Quang Nam-Da Nang	382674	2005-10-02 11:50:11
3773	Biên Hoa	VNM	Dong Nai	282095	2009-01-04 09:01:03
3774	Nha Trang	VNM	Khanh Hoa	221331	2004-08-02 15:36:55
3775	Hue	VNM	Thua Thien-Hue	219149	2004-11-23 05:23:34
3776	Can Tho	VNM	Can Tho	215587	2001-05-09 16:56:58
3777	Cam Pha	VNM	Quang Binh	209086	2010-04-03 07:05:35
3778	Nam Dinh	VNM	Nam Ha	171699	2008-07-02 10:56:14
3779	Quy Nhon	VNM	Binh Dinh	163385	2010-10-25 16:36:48
3780	Vung Tau	VNM	Ba Ria-Vung Tau	145145	2006-10-20 06:57:42
3781	Rach Gia	VNM	Kien Giang	141132	2012-01-07 03:13:31
3782	Long Xuyen	VNM	An Giang	132681	2003-05-11 06:16:33
3783	Thai Nguyen	VNM	Bac Thai	127643	2013-12-18 09:32:02
3784	Hong Gai	VNM	Quang Ninh	127484	2009-11-13 19:42:45
3785	Phan Thiêt	VNM	Binh Thuan	114236	2004-12-24 14:13:45
3786	Cam Ranh	VNM	Khanh Hoa	114041	2007-03-20 11:59:35
3787	Vinh	VNM	Nghe An	112455	2013-08-24 10:29:12
3788	My Tho	VNM	Tien Giang	108404	2014-10-12 13:36:19
3789	Da Lat	VNM	Lam Dong	106409	2003-05-22 23:33:28
3790	Buon Ma Thuot	VNM	Dac Lac	97044	2003-08-04 22:05:37
3791	Tallinn	EST	Harjumaa	403981	2001-06-02 04:16:44
3792	Tartu	EST	Tartumaa	101246	2001-04-27 23:47:02
3793	New York	USA	New York	8008278	2004-02-15 14:13:06
3794	Los Angeles	USA	California	3694820	2002-12-14 22:08:42
3795	Chicago	USA	Illinois	2896016	2006-01-04 18:45:23
3796	Houston	USA	Texas	1953631	2006-03-13 19:50:18
3797	Philadelphia	USA	Pennsylvania	1517550	2008-03-25 11:40:39
3798	Phoenix	USA	Arizona	1321045	2013-06-24 16:21:27
3799	San Diego	USA	California	1223400	2004-11-21 15:49:55
3800	Dallas	USA	Texas	1188580	2014-02-22 14:39:06
3801	San Antonio	USA	Texas	1144646	2005-06-22 16:36:15
3802	Detroit	USA	Michigan	951270	2008-12-04 22:52:27
3803	San Jose	USA	California	894943	2013-02-04 20:33:02
3804	Indianapolis	USA	Indiana	791926	2013-03-25 22:28:42
3805	San Francisco	USA	California	776733	2011-10-16 20:29:39
3806	Jacksonville	USA	Florida	735167	2010-11-20 19:23:14
3807	Columbus	USA	Ohio	711470	2006-09-05 12:10:55
3808	Austin	USA	Texas	656562	2014-07-26 13:54:42
3809	Baltimore	USA	Maryland	651154	2008-01-17 12:18:15
3810	Memphis	USA	Tennessee	650100	2012-06-19 07:53:13
3811	Milwaukee	USA	Wisconsin	596974	2003-12-15 13:50:29
3812	Boston	USA	Massachusetts	589141	2008-10-07 13:16:46
3813	Washington	USA	District of Columbia	572059	2008-02-16 09:45:06
3814	Nashville-Davidson	USA	Tennessee	569891	2010-02-07 11:53:51
3815	El Paso	USA	Texas	563662	2014-04-09 05:48:08
3816	Seattle	USA	Washington	563374	2004-08-23 17:14:33
3817	Denver	USA	Colorado	554636	2008-08-17 16:51:08
3818	Charlotte	USA	North Carolina	540828	2009-12-28 11:13:53
3819	Fort Worth	USA	Texas	534694	2001-03-12 10:08:23
3820	Portland	USA	Oregon	529121	2012-02-22 04:47:28
3821	Oklahoma City	USA	Oklahoma	506132	2010-10-07 10:29:42
3822	Tucson	USA	Arizona	486699	2013-12-10 18:44:04
3823	New Orleans	USA	Louisiana	484674	2009-07-18 07:28:57
3824	Las Vegas	USA	Nevada	478434	2001-06-01 03:16:56
3825	Cleveland	USA	Ohio	478403	2008-06-07 14:04:08
3826	Long Beach	USA	California	461522	2006-12-19 03:42:07
3827	Albuquerque	USA	New Mexico	448607	2004-08-28 13:22:12
3828	Kansas City	USA	Missouri	441545	2007-11-22 21:13:51
3829	Fresno	USA	California	427652	2010-06-07 11:23:07
3830	Virginia Beach	USA	Virginia	425257	2011-01-11 17:46:15
3831	Atlanta	USA	Georgia	416474	2004-11-26 15:49:35
3832	Sacramento	USA	California	407018	2004-09-13 13:21:57
3833	Oakland	USA	California	399484	2014-05-09 23:26:04
3834	Mesa	USA	Arizona	396375	2008-07-25 02:49:40
3835	Tulsa	USA	Oklahoma	393049	2011-10-24 06:53:32
3836	Omaha	USA	Nebraska	390007	2012-11-10 12:48:56
3837	Minneapolis	USA	Minnesota	382618	2009-10-01 18:29:02
3838	Honolulu	USA	Hawaii	371657	2008-11-12 14:17:01
3839	Miami	USA	Florida	362470	2007-05-22 01:45:25
3840	Colorado Springs	USA	Colorado	360890	2012-12-19 23:36:57
3841	Saint Louis	USA	Missouri	348189	2009-05-21 22:18:11
3842	Wichita	USA	Kansas	344284	2001-04-22 05:36:33
3843	Santa Ana	USA	California	337977	2007-08-14 15:36:09
3844	Pittsburgh	USA	Pennsylvania	334563	2013-07-26 10:04:45
3845	Arlington	USA	Texas	332969	2013-06-14 23:13:12
3846	Cincinnati	USA	Ohio	331285	2012-03-22 23:36:26
3847	Anaheim	USA	California	328014	2013-01-23 11:42:25
3848	Toledo	USA	Ohio	313619	2006-07-04 12:59:53
3849	Tampa	USA	Florida	303447	2014-05-26 13:26:31
3850	Buffalo	USA	New York	292648	2007-12-25 09:53:13
3851	Saint Paul	USA	Minnesota	287151	2006-05-03 14:16:05
3852	Corpus Christi	USA	Texas	277454	2003-06-04 01:45:31
3853	Aurora	USA	Colorado	276393	2001-07-26 09:54:54
3854	Raleigh	USA	North Carolina	276093	2006-06-07 18:52:17
3855	Newark	USA	New Jersey	273546	2014-07-14 23:45:25
3856	Lexington-Fayette	USA	Kentucky	260512	2011-02-09 09:54:07
3857	Anchorage	USA	Alaska	260283	2011-11-22 23:06:53
3858	Louisville	USA	Kentucky	256231	2009-11-22 11:58:43
3859	Riverside	USA	California	255166	2011-05-17 22:18:56
3860	Saint Petersburg	USA	Florida	248232	2014-10-26 02:20:47
3861	Bakersfield	USA	California	247057	2001-01-10 17:10:45
3862	Stockton	USA	California	243771	2012-11-24 02:50:49
3863	Birmingham	USA	Alabama	242820	2001-02-04 12:23:33
3864	Jersey City	USA	New Jersey	240055	2002-10-26 13:50:21
3865	Norfolk	USA	Virginia	234403	2009-04-27 19:56:23
3866	Baton Rouge	USA	Louisiana	227818	2009-05-05 08:49:22
3867	Hialeah	USA	Florida	226419	2011-10-07 21:27:56
3868	Lincoln	USA	Nebraska	225581	2010-01-11 07:17:57
3869	Greensboro	USA	North Carolina	223891	2013-06-04 17:26:42
3870	Plano	USA	Texas	222030	2002-07-17 03:17:56
3871	Rochester	USA	New York	219773	2005-09-12 19:23:56
3872	Glendale	USA	Arizona	218812	2001-03-26 07:50:13
3873	Akron	USA	Ohio	217074	2011-02-19 07:11:37
3874	Garland	USA	Texas	215768	2001-06-24 08:46:29
3875	Madison	USA	Wisconsin	208054	2007-07-12 17:33:53
3876	Fort Wayne	USA	Indiana	205727	2006-11-01 21:33:11
3877	Fremont	USA	California	203413	2002-12-23 19:37:41
3878	Scottsdale	USA	Arizona	202705	2010-01-07 05:41:53
3879	Montgomery	USA	Alabama	201568	2010-05-22 06:04:55
3880	Shreveport	USA	Louisiana	200145	2013-09-05 22:40:54
3881	Augusta-Richmond County	USA	Georgia	199775	2003-06-15 08:05:39
3882	Lubbock	USA	Texas	199564	2014-05-19 06:06:33
3883	Chesapeake	USA	Virginia	199184	2006-07-28 09:43:40
3884	Mobile	USA	Alabama	198915	2005-04-04 14:23:12
3885	Des Moines	USA	Iowa	198682	2014-06-13 15:27:46
3886	Grand Rapids	USA	Michigan	197800	2004-07-25 13:31:33
3887	Richmond	USA	Virginia	197790	2002-04-18 06:51:19
3888	Yonkers	USA	New York	196086	2006-03-08 21:28:01
3889	Spokane	USA	Washington	195629	2002-08-18 10:06:05
3890	Glendale	USA	California	194973	2013-12-24 02:03:05
3891	Tacoma	USA	Washington	193556	2007-03-07 17:22:07
3892	Irving	USA	Texas	191615	2004-07-24 23:46:25
3893	Huntington Beach	USA	California	189594	2012-09-25 19:16:28
3894	Modesto	USA	California	188856	2013-07-19 06:21:39
3895	Durham	USA	North Carolina	187035	2012-10-02 11:17:33
3896	Columbus	USA	Georgia	186291	2013-06-15 17:49:51
3897	Orlando	USA	Florida	185951	2006-12-17 04:08:53
3898	Boise City	USA	Idaho	185787	2011-04-18 23:46:06
3899	Winston-Salem	USA	North Carolina	185776	2002-05-26 23:20:25
3900	San Bernardino	USA	California	185401	2002-02-11 01:08:12
3901	Jackson	USA	Mississippi	184256	2011-08-10 14:35:31
3902	Little Rock	USA	Arkansas	183133	2012-01-15 13:23:10
3903	Salt Lake City	USA	Utah	181743	2005-12-23 10:18:43
3904	Reno	USA	Nevada	180480	2003-09-01 06:42:59
3905	Newport News	USA	Virginia	180150	2013-01-06 04:06:43
3906	Chandler	USA	Arizona	176581	2009-09-12 01:47:25
3907	Laredo	USA	Texas	176576	2011-04-24 20:12:14
3908	Henderson	USA	Nevada	175381	2014-04-04 11:07:08
3909	Arlington	USA	Virginia	174838	2004-05-01 19:13:54
3910	Knoxville	USA	Tennessee	173890	2006-02-24 14:55:20
3911	Amarillo	USA	Texas	173627	2008-09-03 05:59:16
3912	Providence	USA	Rhode Island	173618	2002-11-11 18:11:53
3913	Chula Vista	USA	California	173556	2004-09-25 09:35:08
3914	Worcester	USA	Massachusetts	172648	2007-06-08 11:33:31
3915	Oxnard	USA	California	170358	2006-09-11 02:16:42
3916	Dayton	USA	Ohio	166179	2008-09-28 14:28:59
3917	Garden Grove	USA	California	165196	2009-09-23 21:46:52
3918	Oceanside	USA	California	161029	2013-04-18 11:21:35
3919	Tempe	USA	Arizona	158625	2007-08-27 07:29:43
3920	Huntsville	USA	Alabama	158216	2001-04-16 17:29:39
3921	Ontario	USA	California	158007	2011-01-18 03:05:17
3922	Chattanooga	USA	Tennessee	155554	2013-05-20 23:29:12
3923	Fort Lauderdale	USA	Florida	152397	2007-10-02 11:35:44
3924	Springfield	USA	Massachusetts	152082	2003-11-05 22:21:54
3925	Springfield	USA	Missouri	151580	2009-10-03 07:14:41
3926	Santa Clarita	USA	California	151088	2007-01-19 12:10:18
3927	Salinas	USA	California	151060	2014-10-11 11:47:48
3928	Tallahassee	USA	Florida	150624	2007-02-24 22:47:03
3929	Rockford	USA	Illinois	150115	2010-11-12 19:29:33
3930	Pomona	USA	California	149473	2007-10-25 10:23:44
3931	Metairie	USA	Louisiana	149428	2005-04-13 23:39:23
3932	Paterson	USA	New Jersey	149222	2005-09-18 01:40:25
3933	Overland Park	USA	Kansas	149080	2003-02-08 01:50:32
3934	Santa Rosa	USA	California	147595	2005-07-12 10:47:06
3935	Syracuse	USA	New York	147306	2013-07-26 09:08:38
3936	Kansas City	USA	Kansas	146866	2002-05-01 10:49:12
3937	Hampton	USA	Virginia	146437	2013-01-23 14:25:47
3938	Lakewood	USA	Colorado	144126	2011-06-19 17:05:50
3939	Vancouver	USA	Washington	143560	2008-12-13 05:23:16
3940	Irvine	USA	California	143072	2007-08-23 05:53:47
3941	Aurora	USA	Illinois	142990	2008-01-15 03:28:57
3942	Moreno Valley	USA	California	142381	2003-02-23 14:46:32
3943	Pasadena	USA	California	141674	2006-09-16 06:17:25
3944	Hayward	USA	California	140030	2008-12-15 14:42:23
3945	Brownsville	USA	Texas	139722	2003-11-27 18:49:33
3946	Bridgeport	USA	Connecticut	139529	2007-06-05 10:52:05
3947	Hollywood	USA	Florida	139357	2001-08-07 11:37:55
3948	Warren	USA	Michigan	138247	2014-04-09 06:57:46
3949	Torrance	USA	California	137946	2012-01-11 05:49:42
3950	Eugene	USA	Oregon	137893	2008-04-09 22:38:49
3951	Pembroke Pines	USA	Florida	137427	2012-08-11 06:03:38
3952	Salem	USA	Oregon	136924	2014-12-13 18:56:40
3953	Pasadena	USA	Texas	133936	2006-10-05 19:14:45
3954	Escondido	USA	California	133559	2007-08-02 19:58:07
3955	Sunnyvale	USA	California	131760	2013-11-04 23:53:11
3956	Savannah	USA	Georgia	131510	2001-05-06 03:26:47
3957	Fontana	USA	California	128929	2014-08-25 03:52:26
3958	Orange	USA	California	128821	2008-03-23 22:49:41
3959	Naperville	USA	Illinois	128358	2008-02-08 01:55:47
3960	Alexandria	USA	Virginia	128283	2004-02-03 06:17:31
3961	Rancho Cucamonga	USA	California	127743	2011-08-19 21:11:58
3962	Grand Prairie	USA	Texas	127427	2009-03-05 14:19:18
3963	East Los Angeles	USA	California	126379	2009-08-10 01:54:33
3964	Fullerton	USA	California	126003	2014-04-04 02:13:40
3965	Corona	USA	California	124966	2002-05-08 21:42:18
3966	Flint	USA	Michigan	124943	2014-05-26 05:50:27
3967	Paradise	USA	Nevada	124682	2008-09-19 06:09:51
3968	Mesquite	USA	Texas	124523	2002-08-28 01:37:51
3969	Sterling Heights	USA	Michigan	124471	2013-11-27 23:02:01
3970	Sioux Falls	USA	South Dakota	123975	2002-11-09 03:06:41
3971	New Haven	USA	Connecticut	123626	2006-01-08 23:11:33
3972	Topeka	USA	Kansas	122377	2003-04-06 14:07:06
3973	Concord	USA	California	121780	2002-12-09 22:55:41
3974	Evansville	USA	Indiana	121582	2013-09-18 09:28:58
3975	Hartford	USA	Connecticut	121578	2003-12-27 06:57:24
3976	Fayetteville	USA	North Carolina	121015	2010-05-18 08:41:20
3977	Cedar Rapids	USA	Iowa	120758	2006-07-25 10:16:13
3978	Elizabeth	USA	New Jersey	120568	2013-07-20 14:09:33
3979	Lansing	USA	Michigan	119128	2005-06-09 02:31:02
3980	Lancaster	USA	California	118718	2012-11-03 11:16:16
3981	Fort Collins	USA	Colorado	118652	2002-10-23 16:12:17
3982	Coral Springs	USA	Florida	117549	2008-07-19 19:20:04
3983	Stamford	USA	Connecticut	117083	2009-05-22 03:06:37
3984	Thousand Oaks	USA	California	117005	2003-01-26 16:55:26
3985	Vallejo	USA	California	116760	2004-11-23 05:19:51
3986	Palmdale	USA	California	116670	2002-07-24 17:14:48
3987	Columbia	USA	South Carolina	116278	2007-06-19 23:15:45
3988	El Monte	USA	California	115965	2004-05-10 06:05:22
3989	Abilene	USA	Texas	115930	2005-04-12 12:02:23
3990	North Las Vegas	USA	Nevada	115488	2002-03-24 04:26:55
3991	Ann Arbor	USA	Michigan	114024	2009-12-27 15:42:33
3992	Beaumont	USA	Texas	113866	2004-03-06 14:23:33
3993	Waco	USA	Texas	113726	2010-05-16 07:52:09
3994	Macon	USA	Georgia	113336	2009-07-01 06:52:03
3995	Independence	USA	Missouri	113288	2008-09-13 04:23:26
3996	Peoria	USA	Illinois	112936	2006-05-15 22:46:14
3997	Inglewood	USA	California	112580	2005-11-06 06:33:32
3998	Springfield	USA	Illinois	111454	2002-07-05 06:12:20
3999	Simi Valley	USA	California	111351	2010-10-23 07:40:53
4000	Lafayette	USA	Louisiana	110257	2001-07-04 23:11:19
4001	Gilbert	USA	Arizona	109697	2001-09-11 15:10:53
4002	Carrollton	USA	Texas	109576	2014-04-17 13:29:46
4003	Bellevue	USA	Washington	109569	2005-08-19 02:04:21
4004	West Valley City	USA	Utah	108896	2007-05-02 01:19:47
4005	Clarksville	USA	Tennessee	108787	2006-11-21 20:45:45
4006	Costa Mesa	USA	California	108724	2004-06-17 23:15:09
4007	Peoria	USA	Arizona	108364	2003-06-01 08:10:43
4008	South Bend	USA	Indiana	107789	2007-12-15 06:04:58
4009	Downey	USA	California	107323	2007-04-11 18:31:32
4010	Waterbury	USA	Connecticut	107271	2014-01-21 23:30:54
4011	Manchester	USA	New Hampshire	107006	2004-04-09 02:14:27
4012	Allentown	USA	Pennsylvania	106632	2011-05-15 04:20:13
4013	McAllen	USA	Texas	106414	2002-12-23 22:10:27
4014	Joliet	USA	Illinois	106221	2008-05-19 05:02:48
4015	Lowell	USA	Massachusetts	105167	2005-10-03 23:40:07
4016	Provo	USA	Utah	105166	2001-06-07 23:15:50
4017	West Covina	USA	California	105080	2013-10-17 22:51:43
4018	Wichita Falls	USA	Texas	104197	2003-03-19 02:25:31
4019	Erie	USA	Pennsylvania	103717	2008-11-13 11:08:58
4020	Daly City	USA	California	103621	2004-06-22 11:49:21
4021	Citrus Heights	USA	California	103455	2012-07-03 06:08:11
4022	Norwalk	USA	California	103298	2012-05-15 21:17:11
4023	Gary	USA	Indiana	102746	2007-08-13 14:33:41
4024	Berkeley	USA	California	102743	2006-10-15 01:50:35
4025	Santa Clara	USA	California	102361	2011-03-14 13:38:03
4026	Green Bay	USA	Wisconsin	102313	2009-01-14 22:23:23
4027	Cape Coral	USA	Florida	102286	2002-10-21 07:48:39
4028	Arvada	USA	Colorado	102153	2009-11-19 14:37:29
4029	Pueblo	USA	Colorado	102121	2008-03-16 20:03:23
4030	Sandy	USA	Utah	101853	2014-06-01 20:19:27
4031	Athens-Clarke County	USA	Georgia	101489	2011-12-15 09:53:41
4032	Cambridge	USA	Massachusetts	101355	2008-07-17 16:26:32
4033	Westminster	USA	Colorado	100940	2014-11-04 21:03:36
4034	San Buenaventura	USA	California	100916	2005-12-19 08:52:38
4035	Portsmouth	USA	Virginia	100565	2007-03-02 13:05:33
4036	Livonia	USA	Michigan	100545	2012-11-14 08:04:47
4037	Burbank	USA	California	100316	2006-07-07 08:35:05
4038	Clearwater	USA	Florida	99936	2005-05-01 10:18:52
4039	Midland	USA	Texas	98293	2001-02-20 17:50:12
4040	Davenport	USA	Iowa	98256	2014-01-03 19:04:48
4041	Mission Viejo	USA	California	98049	2013-10-18 14:43:49
4042	Miami Beach	USA	Florida	97855	2007-06-28 10:07:57
4043	Sunrise Manor	USA	Nevada	95362	2003-07-26 21:17:07
4044	New Bedford	USA	Massachusetts	94780	2001-03-20 09:42:54
4045	El Cajon	USA	California	94578	2003-07-24 11:06:34
4046	Norman	USA	Oklahoma	94193	2007-08-12 15:22:31
4047	Richmond	USA	California	94100	2012-10-17 19:23:45
4048	Albany	USA	New York	93994	2008-05-25 07:01:08
4049	Brockton	USA	Massachusetts	93653	2014-05-14 04:49:46
4050	Roanoke	USA	Virginia	93357	2010-10-22 01:08:48
4051	Billings	USA	Montana	92988	2003-05-07 01:11:36
4052	Compton	USA	California	92864	2013-03-01 13:40:05
4053	Gainesville	USA	Florida	92291	2014-03-24 23:08:50
4054	Fairfield	USA	California	92256	2010-08-11 23:25:23
4055	Arden-Arcade	USA	California	92040	2005-12-28 09:45:55
4056	San Mateo	USA	California	91799	2005-02-24 07:56:16
4057	Visalia	USA	California	91762	2013-06-16 20:42:29
4058	Boulder	USA	Colorado	91238	2013-03-18 02:37:07
4059	Cary	USA	North Carolina	91213	2013-11-21 14:27:27
4060	Santa Monica	USA	California	91084	2003-07-15 12:19:23
4061	Fall River	USA	Massachusetts	90555	2001-12-17 18:33:22
4062	Kenosha	USA	Wisconsin	89447	2007-11-18 15:07:46
4063	Elgin	USA	Illinois	89408	2004-07-13 13:23:21
4064	Odessa	USA	Texas	89293	2010-05-14 02:32:01
4065	Carson	USA	California	89089	2003-12-02 13:58:09
4066	Charleston	USA	South Carolina	89063	2001-03-09 03:02:17
4067	Charlotte Amalie	VIR	St Thomas	13000	2010-12-13 05:19:19
4068	Harare	ZWE	Harare	1410000	2010-11-14 04:55:44
4069	Bulawayo	ZWE	Bulawayo	621742	2005-10-03 04:25:40
4070	Chitungwiza	ZWE	Harare	274912	2002-07-04 16:43:44
4071	Mount Darwin	ZWE	Harare	164362	2003-12-22 17:45:14
4072	Mutare	ZWE	Manicaland	131367	2002-03-03 11:23:05
4073	Gweru	ZWE	Midlands	128037	2005-12-21 01:14:37
4074	Gaza	PSE	Gaza	353632	2006-03-18 09:53:57
4075	Khan Yunis	PSE	Khan Yunis	123175	2004-04-26 09:37:15
4076	Hebron	PSE	Hebron	119401	2011-04-02 07:43:15
4077	Jabaliya	PSE	North Gaza	113901	2003-12-07 15:31:23
4078	Nablus	PSE	Nablus	100231	2007-07-19 01:21:53
4079	Rafah	PSE	Rafah	92020	2003-01-10 21:09:25
\.


--
-- Data for Name: t_countrylanguage_col; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "t_countrylanguage_col" (cou_code, col_language, col_isofficial, col_percentage) FROM stdin;
AFG	Pashto	t	52.4000015
NLD	Dutch	t	95.5999985
ANT	Papiamento	t	86.1999969
ALB	Albaniana	t	97.9000015
DZA	Arabic	t	86
ASM	Samoan	t	90.5999985
AND	Spanish	f	44.5999985
AGO	Ovimbundu	f	37.2000008
AIA	English	t	0
ATG	Creole English	f	95.6999969
ARE	Arabic	t	42
ARG	Spanish	t	96.8000031
ARM	Armenian	t	93.4000015
ABW	Papiamento	f	76.6999969
AUS	English	t	81.1999969
AZE	Azerbaijani	t	89
BHS	Creole English	f	89.6999969
BHR	Arabic	t	67.6999969
BGD	Bengali	t	97.6999969
BRB	Bajan	f	95.0999985
BEL	Dutch	t	59.2000008
BLZ	English	t	50.7999992
BEN	Fon	f	39.7999992
BMU	English	t	100
BTN	Dzongkha	t	50
BOL	Spanish	t	87.6999969
BIH	Serbo-Croatian	t	99.1999969
BWA	Tswana	f	75.5
BRA	Portuguese	t	97.5
GBR	English	t	97.3000031
VGB	English	t	0
BRN	Malay	t	45.5
BGR	Bulgariana	t	83.1999969
BFA	Mossi	f	50.2000008
BDI	Kirundi	t	98.0999985
CYM	English	t	0
CHL	Spanish	t	89.6999969
COK	Maori	t	0
CRI	Spanish	t	97.5
DJI	Somali	f	43.9000015
DMA	Creole English	f	100
DOM	Spanish	t	98
ECU	Spanish	t	93
EGY	Arabic	t	98.8000031
SLV	Spanish	t	100
ERI	Tigrinja	t	49.0999985
ESP	Spanish	t	74.4000015
ZAF	Zulu	t	22.7000008
ETH	Oromo	f	31
FLK	English	t	0
FJI	Fijian	t	50.7999992
PHL	Pilipino	t	29.2999992
FRO	Faroese	t	100
GAB	Fang	f	35.7999992
GMB	Malinke	f	34.0999985
GEO	Georgiana	t	71.6999969
GHA	Akan	f	52.4000015
GIB	English	t	88.9000015
GRD	Creole English	f	100
GRL	Greenlandic	t	87.5
GLP	Creole French	f	95
GUM	English	t	37.5
GTM	Spanish	t	64.6999969
GIN	Ful	f	38.5999985
GNB	Crioulo	f	36.4000015
GUY	Creole English	f	96.4000015
HTI	Haiti Creole	f	100
HND	Spanish	t	97.1999969
HKG	Canton Chinese	f	88.6999969
SJM	Norwegian	t	0
IDN	Javanese	f	39.4000015
IND	Hindi	t	39.9000015
IRQ	Arabic	t	77.1999969
IRN	Persian	t	45.7000008
IRL	English	t	98.4000015
ISL	Icelandic	t	95.6999969
ISR	Hebrew	t	63.0999985
ITA	Italian	t	94.0999985
TMP	Sunda	f	0
AUT	German	t	92
JAM	Creole English	f	94.1999969
JPN	Japanese	t	99.0999985
YEM	Arabic	t	99.5999985
JOR	Arabic	t	97.9000015
CXR	Chinese	f	0
YUG	Serbo-Croatian	t	75.1999969
KHM	Khmer	t	88.5999985
CMR	Fang	f	19.7000008
CAN	English	t	60.4000015
CPV	Crioulo	f	100
KAZ	Kazakh	t	46
KEN	Kikuyu	f	20.8999996
CAF	Gbaya	f	23.7999992
CHN	Chinese	t	92
KGZ	Kirgiz	t	59.7000008
KIR	Kiribati	t	98.9000015
COL	Spanish	t	99
COM	Comorian	t	75
COG	Kongo	f	51.5
COD	Luba	f	18
CCK	Malay	f	0
PRK	Korean	t	99.9000015
KOR	Korean	t	99.9000015
GRC	Greek	t	98.5
HRV	Serbo-Croatian	t	95.9000015
CUB	Spanish	t	100
KWT	Arabic	t	78.0999985
CYP	Greek	t	74.0999985
LAO	Lao	t	67.1999969
LVA	Latvian	t	55.0999985
LSO	Sotho	t	85
LBN	Arabic	t	93
LBR	Kpelle	f	19.5
LBY	Arabic	t	96
LIE	German	t	89
LTU	Lithuanian	t	81.5999985
LUX	Luxembourgish	t	64.4000015
ESH	Arabic	t	100
MAC	Canton Chinese	f	85.5999985
MDG	Malagasy	t	98.9000015
MKD	Macedonian	t	66.5
MWI	Chichewa	t	58.2999992
MDV	Dhivehi	t	100
MYS	Malay	t	58.4000015
MLI	Bambara	f	31.7999992
MLT	Maltese	t	95.8000031
MAR	Arabic	t	65
MHL	Marshallese	t	96.8000031
MTQ	Creole French	f	96.5999985
MRT	Hassaniya	f	81.6999969
MUS	Creole French	f	70.5999985
MYT	Mahoré	f	41.9000015
MEX	Spanish	t	92.0999985
FSM	Trukese	f	41.5999985
MDA	Romanian	t	61.9000015
MCO	French	t	41.9000015
MNG	Mongolian	t	78.8000031
MSR	English	t	0
MOZ	Makua	f	27.7999992
MMR	Burmese	t	69
NAM	Ovambo	f	50.7000008
NRU	Nauru	t	57.5
NPL	Nepali	t	50.4000015
NIC	Spanish	t	97.5999985
NER	Hausa	f	53.0999985
NGA	Joruba	f	21.3999996
NIU	Niue	f	0
NFK	English	t	0
NOR	Norwegian	t	96.5999985
CIV	Akan	f	30
OMN	Arabic	t	76.6999969
PAK	Punjabi	f	48.2000008
PLW	Palau	t	82.1999969
PAN	Spanish	t	76.8000031
PNG	Papuan Languages	f	78.0999985
PRY	Spanish	t	55.0999985
PER	Spanish	t	79.8000031
PCN	Pitcairnese	f	0
MNP	Philippene Languages	f	34.0999985
PRT	Portuguese	t	99
PRI	Spanish	t	51.2999992
POL	Polish	t	97.5999985
GNQ	Fang	f	84.8000031
QAT	Arabic	t	40.7000008
FRA	French	t	93.5999985
GUF	Creole French	f	94.3000031
PYF	Tahitian	f	46.4000015
REU	Creole French	f	91.5
ROM	Romanian	t	90.6999969
RWA	Rwanda	t	100
SWE	Swedish	t	89.5
SHN	English	t	0
KNA	Creole English	f	100
LCA	Creole French	f	80
VCT	Creole English	f	99.0999985
SPM	French	t	0
DEU	German	t	91.3000031
SLB	Malenasian Languages	f	85.5999985
ZMB	Bemba	f	29.7000008
WSM	Samoan-English	f	52
SMR	Italian	t	100
STP	Crioulo	f	86.3000031
SAU	Arabic	t	95
SEN	Wolof	t	48.0999985
SYC	Seselwa	f	91.3000031
SLE	Mende	f	34.7999992
SGP	Chinese	t	77.0999985
SVK	Slovak	t	85.5999985
SVN	Slovene	t	87.9000015
SOM	Somali	t	98.3000031
LKA	Singali	t	60.2999992
SDN	Arabic	t	49.4000015
FIN	Finnish	t	92.6999969
SUR	Sranantonga	f	81
SWZ	Swazi	t	89.9000015
CHE	German	t	63.5999985
SYR	Arabic	t	90
TJK	Tadzhik	t	62.2000008
TWN	Min	f	66.6999969
TZA	Nyamwesi	f	21.1000004
DNK	Danish	t	93.5
THA	Thai	t	52.5999985
TGO	Ewe	t	23.2000008
TKL	Tokelau	f	0
TON	Tongan	t	98.3000031
TTO	English	f	93.5
TCD	Sara	f	27.7000008
CZE	Czech	t	81.1999969
TUN	Arabic	t	69.9000015
TUR	Turkish	t	87.5999985
TKM	Turkmenian	t	76.6999969
TCA	English	t	0
TUV	Tuvalu	t	92.5
UGA	Ganda	f	18.1000004
UKR	Ukrainian	t	64.6999969
HUN	Hungarian	t	98.5
URY	Spanish	t	95.6999969
NCL	Malenasian Languages	f	45.4000015
NZL	English	t	87
UZB	Uzbek	t	72.5999985
BLR	Belorussian	t	65.5999985
WLF	Wallis	f	0
VUT	Bislama	t	56.5999985
VAT	Italian	t	0
VEN	Spanish	t	96.9000015
RUS	Russian	t	86.5999985
VNM	Vietnamese	t	86.8000031
EST	Estonian	t	65.3000031
USA	English	t	86.1999969
VIR	English	t	81.6999969
UMI	English	t	0
ZWE	Shona	f	72.0999985
PSE	Arabic	f	95.9000015
AFG	Dari	t	32.0999985
NLD	Fries	f	3.70000005
ANT	English	f	7.80000019
ALB	Greek	f	1.79999995
DZA	Berberi	f	14
ASM	English	t	3.0999999
AND	Catalan	t	32.2999992
AGO	Mbundu	f	21.6000004
ATG	English	t	0
ARE	Hindi	f	0
ARG	Italian	f	1.70000005
ARM	Azerbaijani	f	2.5999999
ABW	English	f	9.5
AUS	Italian	f	2.20000005
AZE	Russian	f	3
BHS	Creole French	f	10.3000002
BHR	English	f	0
BGD	Chakma	f	0.400000006
BRB	English	t	0
BEL	French	t	32.5999985
BLZ	Spanish	f	31.6000004
BEN	Joruba	f	12.1999998
BTN	Nepali	f	34.7999992
BOL	Ketua	t	8.10000038
BWA	Shona	f	12.3000002
BRA	German	f	0.5
GBR	Kymri	f	0.899999976
BRN	Malay-English	f	28.7999992
BGR	Turkish	f	9.39999962
BFA	Ful	f	9.69999981
BDI	French	t	0
CHL	Araucan	f	9.60000038
COK	English	f	0
CRI	Creole English	f	2
DJI	Afar	f	34.7999992
DMA	Creole French	f	0
DOM	Creole French	f	2
ECU	Ketua	f	7
EGY	Sinaberberi	f	0
SLV	Nahua	f	0
ERI	Tigre	f	31.7000008
ESP	Catalan	f	16.8999996
ZAF	Xhosa	t	17.7000008
ETH	Amhara	f	30
FJI	Hindi	f	43.7000008
PHL	Cebuano	f	23.2999992
FRO	Danish	t	0
GAB	Punu-sira-nzebi	f	17.1000004
GMB	Ful	f	16.2000008
GEO	Russian	f	8.80000019
GHA	Mossi	f	15.8000002
GIB	Arabic	f	7.4000001
GRL	Danish	t	12.5
GLP	French	t	0
GUM	Chamorro	t	29.6000004
GTM	Quiché	f	10.1000004
GIN	Malinke	f	23.2000008
GNB	Ful	f	16.6000004
GUY	Caribbean	f	2.20000005
HTI	French	t	0
HND	Garifuna	f	1.29999995
HKG	English	t	2.20000005
SJM	Russian	f	0
IDN	Sunda	f	15.8000002
IND	Bengali	f	8.19999981
IRQ	Kurdish	f	19
IRN	Azerbaijani	f	16.7999992
IRL	Irish	t	1.60000002
ISL	English	f	0
ISR	Arabic	t	18
ITA	Sardinian	f	2.70000005
TMP	Portuguese	t	0
AUT	Serbo-Croatian	f	2.20000005
JAM	Hindi	f	1.89999998
JPN	Korean	f	0.5
YEM	Soqutri	f	0
JOR	Circassian	f	1
CXR	English	t	0
YUG	Albaniana	f	16.5
KHM	Vietnamese	f	5.5
CMR	Bamileke-bamum	f	18.6000004
CAN	French	t	23.3999996
CPV	Portuguese	t	0
KAZ	Russian	f	34.7000008
KEN	Luhya	f	13.8000002
CAF	Banda	f	23.5
CHN	Zhuang	f	1.39999998
KGZ	Russian	t	16.2000008
KIR	Tuvalu	f	0.5
COL	Chibcha	f	0.400000006
COM	Comorian-French	f	12.8999996
COG	Teke	f	17.2999992
COD	Kongo	f	16
CCK	English	t	0
PRK	Chinese	f	0.100000001
KOR	Chinese	f	0.100000001
GRC	Turkish	f	0.899999976
HRV	Slovene	f	0
KWT	English	f	0
CYP	Turkish	t	22.3999996
LAO	Mon-khmer	f	16.5
LVA	Russian	f	32.5
LSO	Zulu	f	15
LBN	Armenian	f	5.9000001
LBR	Bassa	f	13.6999998
LBY	Berberi	f	1
LIE	Italian	f	2.5
LTU	Russian	f	8.10000038
LUX	Portuguese	f	13
MAC	Portuguese	t	2.29999995
MDG	French	t	0
MKD	Albaniana	f	22.8999996
MWI	Lomwe	f	18.3999996
MDV	English	f	0
MYS	Chinese	f	9
MLI	Ful	f	13.8999996
MLT	English	t	2.0999999
MAR	Berberi	f	33
MHL	English	t	0
MTQ	French	t	0
MRT	Wolof	f	6.5999999
MUS	Bhojpuri	f	21.1000004
MYT	French	t	20.2999992
MEX	Náhuatl	f	1.79999995
FSM	Pohnpei	f	23.7999992
MDA	Russian	f	23.2000008
MCO	Monegasque	f	16.1000004
MNG	Kazakh	f	5.9000001
MOZ	Tsonga	f	12.3999996
MMR	Shan	f	8.5
NAM	Nama	f	12.3999996
NRU	Kiribati	f	17.8999996
NPL	Maithili	f	11.8999996
NIC	Miskito	f	1.60000002
NER	Songhai-zerma	f	21.2000008
NGA	Hausa	f	21.1000004
NIU	English	t	0
NOR	English	f	0.5
CIV	Gur	f	11.6999998
OMN	Balochi	f	0
PAK	Pashto	f	13.1000004
PLW	Philippene Languages	f	9.19999981
PAN	Creole English	f	14
PNG	Malenasian Languages	f	20
PRY	Guaraní	t	40.0999985
PER	Ketua	t	16.3999996
MNP	Chamorro	f	30
PRI	English	f	47.4000015
POL	German	f	1.29999995
GNQ	Bubi	f	8.69999981
QAT	Urdu	f	0
FRA	Arabic	f	2.5
GUF	Indian Languages	f	1.89999998
PYF	French	t	40.7999992
REU	Chinese	f	2.79999995
ROM	Hungarian	f	7.19999981
RWA	French	t	0
SWE	Finnish	f	2.4000001
KNA	English	t	0
LCA	English	t	20
VCT	English	t	0
DEU	Turkish	f	2.5999999
SLB	Papuan Languages	f	8.60000038
ZMB	Tongan	f	11
WSM	Samoan	t	47.5
STP	French	f	0.699999988
SEN	Ful	f	21.7000008
SYC	English	t	3.79999995
SLE	Temne	f	31.7999992
SGP	Malay	t	14.1000004
SVK	Hungarian	f	10.5
SVN	Serbo-Croatian	f	7.9000001
SOM	Arabic	t	0
LKA	Tamil	t	19.6000004
SDN	Dinka	f	11.5
FIN	Swedish	t	5.69999981
SUR	Hindi	f	0
SWZ	Zulu	f	2
CHE	French	t	19.2000008
SYR	Kurdish	f	9
TJK	Uzbek	f	23.2000008
TWN	Mandarin Chinese	t	20.1000004
TZA	Swahili	t	8.80000019
DNK	Turkish	f	0.800000012
THA	Lao	f	26.8999996
TGO	Kabyé	t	13.8000002
TKL	English	t	0
TON	English	t	0
TTO	Hindi	f	3.4000001
TCD	Arabic	t	12.3000002
CZE	Moravian	f	12.8999996
TUN	Arabic-French	f	26.2999992
TUR	Kurdish	f	10.6000004
TKM	Uzbek	f	9.19999981
TUV	Kiribati	f	7.5
UGA	Nkole	f	10.6999998
UKR	Russian	f	32.9000015
HUN	Romani	f	0.5
NCL	French	t	34.2999992
NZL	Maori	f	4.30000019
UZB	Russian	f	10.8999996
BLR	Russian	t	32
WLF	Futuna	f	0
VUT	English	t	28.2999992
VEN	Goajiro	f	0.400000006
RUS	Tatar	f	3.20000005
VNM	Tho	f	1.79999995
EST	Russian	f	27.7999992
USA	Spanish	f	7.5
VIR	Spanish	f	13.3000002
ZWE	Ndebele	f	16.2000008
PSE	Hebrew	f	4.0999999
AFG	Uzbek	f	8.80000019
NLD	Arabic	f	0.899999976
ANT	Dutch	t	0
ALB	Macedonian	f	0.100000001
ASM	Tongan	f	3.0999999
AND	Portuguese	f	10.8000002
AGO	Kongo	f	13.1999998
ARG	Indian Languages	f	0.300000012
ABW	Spanish	f	7.4000001
AUS	Greek	f	1.60000002
AZE	Lezgian	f	2.29999995
BGD	Marma	f	0.200000003
BEL	Italian	f	2.4000001
BLZ	Maya Languages	f	9.60000038
BEN	Adja	f	11.1000004
BTN	Asami	f	15.1999998
BOL	Aimará	t	3.20000005
BWA	San	f	3.5
BRA	Italian	f	0.400000006
GBR	Gaeli	f	0.100000001
BRN	Chinese	f	9.30000019
BGR	Romani	f	3.70000005
BFA	Gurma	f	5.69999981
BDI	Swahili	f	0
CHL	Aimará	f	0.5
CRI	Chibcha	f	0.300000012
DJI	Arabic	t	10.6000004
ERI	Afar	f	4.30000019
ESP	Galecian	f	6.4000001
ZAF	Afrikaans	t	14.3000002
ETH	Tigrinja	f	7.19999981
PHL	Ilocano	f	9.30000019
GAB	Mpongwe	f	14.6000004
GMB	Wolof	f	12.6000004
GEO	Armenian	f	6.80000019
GHA	Ewe	f	11.8999996
GUM	Philippene Languages	f	19.7000008
GTM	Cakchiquel	f	8.89999962
GIN	Susu	f	11
GNB	Balante	f	14.6000004
GUY	Arawakan	f	1.39999998
HND	Creole English	f	0.200000003
HKG	Fukien	f	1.89999998
IDN	Malay	t	12.1000004
IND	Telugu	f	7.80000019
IRQ	Azerbaijani	f	1.70000005
IRN	Kurdish	f	9.10000038
ISR	Russian	f	8.89999962
ITA	Friuli	f	1.20000005
AUT	Turkish	f	1.5
JPN	Chinese	f	0.200000003
JOR	Armenian	f	1
YUG	Hungarian	f	3.4000001
KHM	Chinese	f	3.0999999
CMR	Duala	f	10.8999996
CAN	Chinese	f	2.5
KAZ	Ukrainian	f	5
KEN	Luo	f	12.8000002
CAF	Mandjia	f	14.8000002
CHN	Mantu	f	0.899999976
KGZ	Uzbek	f	14.1000004
COL	Creole English	f	0.100000001
COM	Comorian-madagassi	f	5.5
COG	Mboshi	f	11.3999996
COD	Mongo	f	13.5
LAO	Thai	f	7.80000019
LVA	Belorussian	f	4.0999999
LSO	English	t	0
LBN	French	f	0
LBR	Grebo	f	8.89999962
LIE	Turkish	f	2.5
LTU	Polish	f	7
LUX	Italian	f	4.5999999
MAC	Mandarin Chinese	f	1.20000005
MKD	Turkish	f	4
MWI	Yao	f	13.1999998
MYS	Tamil	f	3.9000001
MLI	Senufo and Minianka	f	12
MRT	Tukulor	f	5.4000001
MUS	French	f	3.4000001
MYT	Malagasy	f	16.1000004
MEX	Yucatec	f	1.10000002
FSM	Mortlock	f	7.5999999
MDA	Ukrainian	f	8.60000038
MCO	Italian	f	16.1000004
MNG	Dorbet	f	2.70000005
MOZ	Sena	f	9.39999962
MMR	Karen	f	6.19999981
NAM	Kavango	f	9.69999981
NRU	Chinese	f	8.5
NPL	Bhojpuri	f	7.5
NIC	Creole English	f	0.5
NER	Tamashek	f	10.3999996
NGA	Ibo	f	18.1000004
NOR	Danish	f	0.400000006
CIV	Malinke	f	11.3999996
PAK	Sindhi	f	11.8000002
PLW	English	t	3.20000005
PAN	Guaymí	f	5.30000019
PRY	Portuguese	f	3.20000005
PER	Aimará	t	2.29999995
MNP	Chinese	f	7.0999999
POL	Ukrainian	f	0.600000024
FRA	Portuguese	f	1.20000005
PYF	Chinese	f	2.9000001
REU	Comorian	f	2.79999995
ROM	Romani	t	0.699999988
SWE	Southern Slavic Languages	f	1.29999995
DEU	Southern Slavic Languages	f	1.39999998
SLB	Polynesian Languages	f	3.79999995
ZMB	Nyanja	f	7.80000019
WSM	English	t	0.600000024
SEN	Serer	f	12.5
SYC	French	t	1.29999995
SLE	Limba	f	8.30000019
SGP	Tamil	t	7.4000001
SVK	Romani	f	1.70000005
SVN	Hungarian	f	0.5
LKA	Mixed Languages	f	19.6000004
SDN	Nubian Languages	f	8.10000038
FIN	Russian	f	0.400000006
CHE	Italian	t	7.69999981
TJK	Russian	f	9.69999981
TWN	Hakka	f	11
TZA	Hehet	f	6.9000001
DNK	Arabic	f	0.699999988
THA	Chinese	f	12.1000004
TGO	Watyi	f	10.3000002
TTO	Creole English	f	2.9000001
TCD	Mayo-kebbi	f	11.5
CZE	Slovak	f	3.0999999
TUN	Arabic-French-English	f	3.20000005
TUR	Arabic	f	1.39999998
TKM	Russian	f	6.69999981
TUV	English	t	0
UGA	Kiga	f	8.30000019
UKR	Romanian	f	0.699999988
HUN	German	f	0.400000006
NCL	Polynesian Languages	f	11.6000004
UZB	Tadzhik	f	4.4000001
BLR	Ukrainian	f	1.29999995
VUT	French	t	14.1999998
VEN	Warrau	f	0.100000001
RUS	Ukrainian	f	1.29999995
VNM	Thai	f	1.60000002
EST	Ukrainian	f	2.79999995
USA	French	f	0.699999988
VIR	French	f	2.5
ZWE	English	t	2.20000005
AFG	Turkmenian	f	1.89999998
NLD	Turkish	f	0.800000012
AND	French	f	6.19999981
AGO	Luimbe-nganguela	f	5.4000001
ABW	Dutch	t	5.30000019
AUS	Canton Chinese	f	1.10000002
AZE	Armenian	f	2
BGD	Garo	f	0.100000001
BEL	Arabic	f	1.60000002
BLZ	Garifuna	f	6.80000019
BEN	Aizo	f	8.69999981
BOL	Guaraní	f	0.100000001
BWA	Khoekhoe	f	2.5
BRA	Japanese	f	0.400000006
BRN	English	f	3.0999999
BGR	Macedonian	f	2.5999999
BFA	Busansi	f	3.5
CHL	Rapa nui	f	0.200000003
CRI	Chinese	f	0.200000003
ERI	Hadareb	f	3.79999995
ESP	Basque	f	1.60000002
ZAF	Northsotho	f	9.10000038
ETH	Gurage	f	4.69999981
PHL	Hiligaynon	f	9.10000038
GAB	Mbete	f	13.8000002
GMB	Diola	f	9.19999981
GEO	Azerbaijani	f	5.5
GHA	Ga-adangme	f	7.80000019
GUM	Korean	f	3.29999995
GTM	Kekchí	f	4.9000001
GIN	Kissi	f	6
GNB	Portuguese	t	8.10000038
HND	Miskito	f	0.200000003
HKG	Hakka	f	1.60000002
IDN	Madura	f	4.30000019
IND	Marathi	f	7.4000001
IRQ	Assyrian	f	0.800000012
IRN	Gilaki	f	5.30000019
ITA	French	f	0.5
AUT	Hungarian	f	0.400000006
JPN	English	f	0.100000001
YUG	Romani	f	1.39999998
KHM	Tam	f	2.4000001
CMR	Ful	f	9.60000038
CAN	Italian	f	1.70000005
KAZ	German	f	3.0999999
KEN	Kamba	f	11.1999998
CAF	Ngbaka	f	7.5
CHN	Hui	f	0.800000012
KGZ	Ukrainian	f	1.70000005
COL	Arawakan	f	0.100000001
COM	Comorian-Arabic	f	1.60000002
COG	Mbete	f	4.80000019
COD	Rwanda	f	10.3000002
LAO	Lao-Soung	f	5.19999981
LVA	Ukrainian	f	2.9000001
LBR	Gio	f	7.9000001
LTU	Belorussian	f	1.39999998
LUX	French	t	4.19999981
MAC	English	f	0.5
MKD	Romani	f	2.29999995
MWI	Ngoni	f	6.69999981
MYS	Iban	f	2.79999995
MLI	Soninke	f	8.69999981
MRT	Soninke	f	2.70000005
MUS	Hindi	f	1.20000005
MEX	Zapotec	f	0.600000024
FSM	Kosrean	f	7.30000019
MDA	Gagauzi	f	3.20000005
MCO	English	f	6.5
MNG	Bajad	f	1.89999998
MOZ	Lomwe	f	7.80000019
MMR	Rakhine	f	4.5
NAM	Afrikaans	f	9.5
NRU	Tuvalu	f	8.5
NPL	Tharu	f	5.4000001
NIC	Sumo	f	0.200000003
NER	Ful	f	9.69999981
NGA	Ful	f	11.3000002
NOR	Swedish	f	0.300000012
CIV	Kru	f	10.5
PAK	Saraiki	f	9.80000019
PLW	Chinese	f	1.60000002
PAN	Cuna	f	2
PRY	German	f	0.899999976
MNP	Korean	f	6.5
POL	Belorussian	f	0.5
FRA	Italian	f	0.400000006
REU	Malagasy	f	1.39999998
ROM	German	f	0.400000006
SWE	Arabic	f	0.800000012
DEU	Italian	f	0.699999988
ZMB	Lozi	f	6.4000001
SEN	Diola	f	5
SLE	Kono-vai	f	5.0999999
SVK	Czech and Moravian	f	1.10000002
SDN	Beja	f	6.4000001
FIN	Estonian	f	0.200000003
CHE	Romansh	t	0.600000024
TWN	Ami	f	0.600000024
TZA	Haya	f	5.9000001
DNK	German	f	0.5
THA	Malay	f	3.5999999
TGO	Kotokoli	f	5.69999981
TCD	Kanem-bornu	f	9
CZE	Polish	f	0.600000024
TKM	Kazakh	f	2
UGA	Soga	f	8.19999981
UKR	Bulgariana	f	0.300000012
HUN	Serbo-Croatian	f	0.200000003
UZB	Kazakh	f	3.79999995
BLR	Polish	f	0.600000024
RUS	Chuvash	f	0.899999976
VNM	Muong	f	1.5
EST	Belorussian	f	1.39999998
USA	German	f	0.699999988
ZWE	Nyanja	f	2.20000005
AFG	Balochi	f	0.899999976
AGO	Nyaneka-nkhumbi	f	5.4000001
AUS	Arabic	f	1
BGD	Khasi	f	0.100000001
BEL	German	t	1
BEN	Bariba	f	8.69999981
BWA	Ndebele	f	1.29999995
BRA	Indian Languages	f	0.200000003
BFA	Dagara	f	3.0999999
ERI	Bilin	f	3
ZAF	English	t	8.5
ETH	Somali	f	4.0999999
PHL	Bicol	f	5.69999981
GMB	Soninke	f	7.5999999
GEO	Osseetti	f	2.4000001
GHA	Gurma	f	3.29999995
GUM	Japanese	f	2
GTM	Mam	f	2.70000005
GIN	Kpelle	f	4.5999999
GNB	Malinke	f	6.9000001
HKG	Chiu chau	f	1.39999998
IDN	Minangkabau	f	2.4000001
IND	Tamil	f	6.30000019
IRQ	Persian	f	0.800000012
IRN	Luri	f	4.30000019
ITA	German	f	0.5
AUT	Slovene	f	0.400000006
JPN	Philippene Languages	f	0.100000001
YUG	Slovak	f	0.699999988
CMR	Tikar	f	7.4000001
CAN	German	f	1.60000002
KAZ	Uzbek	f	2.29999995
KEN	Kalenjin	f	10.8000002
CAF	Sara	f	6.4000001
CHN	Miao	f	0.699999988
KGZ	Tatar	f	1.29999995
COL	Caribbean	f	0.100000001
COM	Comorian-Swahili	f	0.5
COG	Punu	f	2.9000001
COD	Zande	f	6.0999999
LVA	Polish	f	2.0999999
LBR	Kru	f	7.19999981
LTU	Ukrainian	f	1.10000002
LUX	German	t	2.29999995
MKD	Serbo-Croatian	f	2
MYS	English	f	1.60000002
MLI	Tamashek	f	7.30000019
MRT	Ful	f	1.20000005
MUS	Tamil	f	0.800000012
MEX	Mixtec	f	0.600000024
FSM	Yap	f	5.80000019
MDA	Bulgariana	f	1.60000002
MNG	Buryat	f	1.70000005
MOZ	Shona	f	6.5
MMR	Mon	f	2.4000001
NAM	Herero	f	8
NRU	English	t	7.5
NPL	Tamang	f	4.9000001
NER	Kanuri	f	4.4000001
NGA	Ibibio	f	5.5999999
NOR	Saame	f	0
CIV	[South]Mande	f	7.69999981
PAK	Urdu	t	7.5999999
PAN	Embera	f	0.600000024
MNP	English	t	4.80000019
FRA	Spanish	f	0.400000006
REU	Tamil	f	0
ROM	Ukrainian	f	0.300000012
SWE	Spanish	f	0.600000024
DEU	Greek	f	0.400000006
ZMB	Chewa	f	5.69999981
SEN	Malinke	f	3.79999995
SLE	Bullom-sherbro	f	3.79999995
SVK	Ukrainian and Russian	f	0.600000024
SDN	Nuer	f	4.9000001
FIN	Saame	f	0
TWN	Atayal	f	0.400000006
TZA	Makonde	f	5.9000001
DNK	English	f	0.300000012
THA	Khmer	f	1.29999995
TGO	Ane	f	5.69999981
TCD	Ouaddai	f	8.69999981
CZE	German	f	0.5
UGA	Teso	f	6
UKR	Hungarian	f	0.300000012
HUN	Romanian	f	0.100000001
UZB	Karakalpak	f	2
RUS	Bashkir	f	0.699999988
VNM	Chinese	f	1.39999998
EST	Finnish	f	0.699999988
USA	Italian	f	0.600000024
AGO	Chokwe	f	4.19999981
AUS	Vietnamese	f	0.800000012
BGD	Santhali	f	0.100000001
BEL	Turkish	f	0.899999976
BEN	Somba	f	6.69999981
BFA	Dyula	f	2.5999999
ERI	Saho	f	3
ZAF	Tswana	f	8.10000038
ETH	Sidamo	f	3.20000005
PHL	Waray-waray	f	3.79999995
GEO	Abhyasi	f	1.70000005
GHA	Joruba	f	1.29999995
GIN	Yalunka	f	2.9000001
GNB	Mandyako	f	4.9000001
IDN	Batakki	f	2.20000005
IND	Urdu	f	5.0999999
IRN	Mazandarani	f	3.5999999
ITA	Albaniana	f	0.200000003
AUT	Polish	f	0.200000003
JPN	Ainu	f	0
YUG	Macedonian	f	0.5
CMR	Mandara	f	5.69999981
CAN	Polish	f	0.699999988
KAZ	Tatar	f	2
KEN	Gusii	f	6.0999999
CAF	Mbum	f	6.4000001
CHN	Uighur	f	0.600000024
KGZ	Kazakh	f	0.800000012
COG	Sango	f	2.5999999
COD	Ngala and Bangi	f	5.80000019
LVA	Lithuanian	f	1.20000005
LBR	Mano	f	7.19999981
MYS	Dusun	f	1.10000002
MLI	Songhai	f	6.9000001
MRT	Zenaga	f	1.20000005
MUS	Marathi	f	0.699999988
MEX	Otomí	f	0.400000006
FSM	Wolea	f	3.70000005
MNG	Dariganga	f	1.39999998
MOZ	Tswa	f	6
MMR	Chin	f	2.20000005
NAM	Caprivi	f	4.69999981
NPL	Newari	f	3.70000005
NGA	Kanuri	f	4.0999999
PAK	Balochi	f	3
PAN	Arabic	f	0.600000024
MNP	Carolinian	f	4.80000019
FRA	Turkish	f	0.400000006
ROM	Serbo-Croatian	f	0.100000001
SWE	Norwegian	f	0.5
DEU	Polish	f	0.300000012
ZMB	Nsenga	f	4.30000019
SEN	Soninke	f	1.29999995
SLE	Ful	f	3.79999995
SDN	Zande	f	2.70000005
TWN	Paiwan	f	0.300000012
TZA	Nyakusa	f	5.4000001
DNK	Swedish	f	0.300000012
THA	Kuy	f	1.10000002
TGO	Moba	f	5.4000001
TCD	Hadjarai	f	6.69999981
CZE	Silesiana	f	0.400000006
UGA	Lango	f	5.9000001
UKR	Belorussian	f	0.300000012
HUN	Slovak	f	0.100000001
UZB	Tatar	f	1.79999995
RUS	Chechen	f	0.600000024
VNM	Khmer	f	1.39999998
USA	Chinese	f	0.600000024
AGO	Luvale	f	3.5999999
AUS	Serbo-Croatian	f	0.600000024
BGD	Tripuri	f	0.100000001
BEN	Ful	f	5.5999999
ZAF	Southsotho	f	7.5999999
ETH	Walaita	f	2.79999995
PHL	Pampango	f	3
GIN	Loma	f	2.29999995
IDN	Bugi	f	2.20000005
IND	Gujarati	f	4.80000019
IRN	Balochi	f	2.29999995
ITA	Slovene	f	0.200000003
AUT	Czech	f	0.200000003
CMR	Maka	f	4.9000001
CAN	Spanish	f	0.699999988
KEN	Meru	f	5.5
CHN	Yi	f	0.600000024
KGZ	Tadzhik	f	0.800000012
COD	Rundi	f	3.79999995
LBR	Loma	f	5.80000019
MOZ	Chuabo	f	5.69999981
MMR	Kachin	f	1.39999998
NAM	San	f	1.89999998
NPL	Hindi	f	3
NGA	Edo	f	3.29999995
PAK	Hindko	f	2.4000001
SLE	Kuranko	f	3.4000001
SDN	Bari	f	2.5
TZA	Chaga and Pare	f	4.9000001
DNK	Norwegian	f	0.300000012
TGO	Naudemba	f	4.0999999
TCD	Tandjile	f	6.5
CZE	Romani	f	0.300000012
UGA	Lugbara	f	4.69999981
UKR	Polish	f	0.100000001
RUS	Mordva	f	0.5
VNM	Nung	f	1.10000002
USA	Tagalog	f	0.400000006
AGO	Ambo	f	2.4000001
AUS	German	f	0.600000024
ZAF	Tsonga	f	4.30000019
PHL	Pangasinan	f	1.79999995
IDN	Banja	f	1.79999995
IND	Kannada	f	3.9000001
IRN	Arabic	f	2.20000005
ITA	Romani	f	0.200000003
AUT	Romanian	f	0.200000003
CMR	Masana	f	3.9000001
CAN	Portuguese	f	0.699999988
KEN	Nyika	f	4.80000019
CHN	Tujia	f	0.5
COD	Teke	f	2.70000005
LBR	Malinke	f	5.0999999
MOZ	Ronga	f	3.70000005
MMR	Kayah	f	0.400000006
NAM	German	f	0.899999976
NGA	Tiv	f	2.29999995
PAK	Brahui	f	1.20000005
SLE	Yalunka	f	3.4000001
SDN	Fur	f	2.0999999
TZA	Luguru	f	4.9000001
TGO	Gurma	f	3.4000001
TCD	Gorane	f	6.19999981
CZE	Hungarian	f	0.200000003
UGA	Gisu	f	4.5
RUS	Kazakh	f	0.400000006
VNM	Miao	f	0.899999976
USA	Polish	f	0.300000012
AGO	Luchazi	f	2.4000001
ZAF	Swazi	f	2.5
PHL	Maguindanao	f	1.39999998
IDN	Bali	f	1.70000005
IND	Malajalam	f	3.5999999
IRN	Bakhtyari	f	1.70000005
CAN	Punjabi	f	0.699999988
KEN	Masai	f	1.60000002
CHN	Mongolian	f	0.400000006
COD	Boa	f	2.29999995
MOZ	Marendje	f	3.5
NGA	Ijo	f	1.79999995
SDN	Chilluk	f	1.70000005
TZA	Shambala	f	4.30000019
UGA	Acholi	f	4.4000001
RUS	Avarian	f	0.400000006
VNM	Man	f	0.699999988
USA	Korean	f	0.300000012
ZAF	Venda	f	2.20000005
PHL	Maranao	f	1.29999995
IND	Orija	f	3.29999995
IRN	Turkmenian	f	1.60000002
CAN	Ukrainian	f	0.600000024
KEN	Turkana	f	1.39999998
CHN	Tibetan	f	0.400000006
COD	Chokwe	f	1.79999995
MOZ	Nyanja	f	3.29999995
NGA	Bura	f	1.60000002
SDN	Lotuko	f	1.5
TZA	Gogo	f	3.9000001
UGA	Rwanda	f	3.20000005
RUS	Mari	f	0.400000006
USA	Vietnamese	f	0.200000003
ZAF	Ndebele	f	1.5
IND	Punjabi	f	2.79999995
CAN	Dutch	f	0.5
CHN	Puyi	f	0.200000003
TZA	Ha	f	3.5
RUS	Udmur	f	0.300000012
USA	Japanese	f	0.200000003
IND	Asami	f	1.5
CAN	Eskimo Languages	f	0.100000001
CHN	Dong	f	0.200000003
RUS	Belorussian	f	0.300000012
USA	Portuguese	f	0.200000003
\.


--
-- Data for Name: t_country_cou; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY "t_country_cou" (cou_code, cou_name, cou_continent, cou_region, cou_surfacearea, cou_indepyear, cou_population, cou_lifeexpectancy, cou_gnp, cou_gnpold, cou_localname, cou_governmentform, cou_head_of_state, cou_capital, cou_code2) FROM stdin;
AFG	Afghanistan	Asia	Southern and Central Asia	652090	1919	22720000	45.9000015	5976.00	\N	Afganistan/Afqanestan	Islamic Emirate	Mohammad Omar	1	AF
NLD	Netherlands	Europe	Western Europe	41526	1581	15864000	78.3000031	371362.00	360478.00	Nederland	Constitutional Monarchy	Beatrix	5	NL
ANT	Netherlands Antilles	North America	Caribbean	800	\N	217000	74.6999969	1941.00	\N	Nederlandse Antillen	Nonmetropolitan Territory of The Netherlands	Beatrix	33	AN
ALB	Albania	Europe	Southern Europe	28748	1912	3401200	71.5999985	3205.00	2500.00	Shqipëria	Republic	Rexhep Mejdani	34	AL
DZA	Algeria	Africa	Northern Africa	2381741	1962	31471000	69.6999969	49982.00	46966.00	Al-Jazair/Algérie	Republic	Abdelaziz Bouteflika	35	DZ
ASM	American Samoa	Oceania	Polynesia	199	\N	68000	75.0999985	334.00	\N	Amerika Samoa	US Territory	George W. Bush	54	AS
AND	Andorra	Europe	Southern Europe	468	1278	78000	83.5	1630.00	\N	Andorra	Parliamentary Coprincipality		55	AD
AGO	Angola	Africa	Central Africa	1246700	1975	12878000	38.2999992	6648.00	7984.00	Angola	Republic	José Eduardo dos Santos	56	AO
AIA	Anguilla	North America	Caribbean	96	\N	8000	76.0999985	63.20	\N	Anguilla	Dependent Territory of the UK	Elisabeth II	62	AI
ATG	Antigua and Barbuda	North America	Caribbean	442	1981	68000	70.5	612.00	584.00	Antigua and Barbuda	Constitutional Monarchy	Elisabeth II	63	AG
ARE	United Arab Emirates	Asia	Middle East	83600	1971	2441000	74.0999985	37966.00	36846.00	Al-Imarat al-´Arabiya al-Muttahida	Emirate Federation	Zayid bin Sultan al-Nahayan	65	AE
ARG	Argentina	South America	South America	2780400	1816	37032000	75.0999985	340238.00	323310.00	Argentina	Federal Republic	Fernando de la Rúa	69	AR
ARM	Armenia	Asia	Middle East	29800	1991	3520000	66.4000015	1813.00	1627.00	Hajastan	Republic	Robert Kotarjan	126	AM
ABW	Aruba	North America	Caribbean	193	\N	103000	78.4000015	828.00	793.00	Aruba	Nonmetropolitan Territory of The Netherlands	Beatrix	129	AW
AUS	Australia	Oceania	Australia and New Zealand	7741220	1901	18886000	79.8000031	351182.00	392911.00	Australia	Constitutional Monarchy, Federation	Elisabeth II	135	AU
AZE	Azerbaijan	Asia	Middle East	86600	1991	7734000	62.9000015	4127.00	4100.00	Azärbaycan	Federal Republic	Heydär Äliyev	144	AZ
BHS	Bahamas	North America	Caribbean	13878	1973	307000	71.0999985	3527.00	3347.00	The Bahamas	Constitutional Monarchy	Elisabeth II	148	BS
BHR	Bahrain	Asia	Middle East	694	1971	617000	73	6366.00	6097.00	Al-Bahrayn	Monarchy (Emirate)	Hamad ibn Isa al-Khalifa	149	BH
BGD	Bangladesh	Asia	Southern and Central Asia	143998	1971	129155000	60.2000008	32852.00	31966.00	Bangladesh	Republic	Shahabuddin Ahmad	150	BD
BRB	Barbados	North America	Caribbean	430	1966	270000	73	2223.00	2186.00	Barbados	Constitutional Monarchy	Elisabeth II	174	BB
BEL	Belgium	Europe	Western Europe	30518	1830	10239000	77.8000031	249704.00	243948.00	België/Belgique	Constitutional Monarchy, Federation	Albert II	179	BE
BLZ	Belize	North America	Central America	22696	1981	241000	70.9000015	630.00	616.00	Belize	Constitutional Monarchy	Elisabeth II	185	BZ
BEN	Benin	Africa	Western Africa	112622	1960	6097000	50.2000008	2357.00	2141.00	Bénin	Republic	Mathieu Kérékou	187	BJ
BMU	Bermuda	North America	North America	53	\N	65000	76.9000015	2328.00	2190.00	Bermuda	Dependent Territory of the UK	Elisabeth II	191	BM
BTN	Bhutan	Asia	Southern and Central Asia	47000	1910	2124000	52.4000015	372.00	383.00	Druk-Yul	Monarchy	Jigme Singye Wangchuk	192	BT
BOL	Bolivia	South America	South America	1098581	1825	8329000	63.7000008	8571.00	7967.00	Bolivia	Republic	Hugo Bánzer Suárez	194	BO
BIH	Bosnia and Herzegovina	Europe	Southern Europe	51197	1992	3972000	71.5	2841.00	\N	Bosna i Hercegovina	Federal Republic	Ante Jelavic	201	BA
BWA	Botswana	Africa	Southern Africa	581730	1966	1622000	39.2999992	4834.00	4935.00	Botswana	Republic	Festus G. Mogae	204	BW
BRA	Brazil	South America	South America	8547403	1822	170115000	62.9000015	776739.00	804108.00	Brasil	Federal Republic	Fernando Henrique Cardoso	211	BR
GBR	United Kingdom	Europe	British Islands	242900	1066	59623400	77.6999969	1378330.00	1296830.00	United Kingdom	Constitutional Monarchy	Elisabeth II	456	GB
VGB	Virgin Islands, British	North America	Caribbean	151	\N	21000	75.4000015	612.00	573.00	British Virgin Islands	Dependent Territory of the UK	Elisabeth II	537	VG
BRN	Brunei	Asia	Southeast Asia	5765	1984	328000	73.5999985	11705.00	12460.00	Brunei Darussalam	Monarchy (Sultanate)	Haji Hassan al-Bolkiah	538	BN
BGR	Bulgaria	Europe	Eastern Europe	110994	1908	8190900	70.9000015	12178.00	10169.00	Balgarija	Republic	Petar Stojanov	539	BG
BFA	Burkina Faso	Africa	Western Africa	274000	1960	11937000	46.7000008	2425.00	2201.00	Burkina Faso	Republic	Blaise Compaoré	549	BF
BDI	Burundi	Africa	Eastern Africa	27834	1962	6695000	46.2000008	903.00	982.00	Burundi/Uburundi	Republic	Pierre Buyoya	552	BI
CYM	Cayman Islands	North America	Caribbean	264	\N	38000	78.9000015	1263.00	1186.00	Cayman Islands	Dependent Territory of the UK	Elisabeth II	553	KY
CHL	Chile	South America	South America	756626	1810	15211000	75.6999969	72949.00	75780.00	Chile	Republic	Ricardo Lagos Escobar	554	CL
COK	Cook Islands	Oceania	Polynesia	236	\N	20000	71.0999985	100.00	\N	The Cook Islands	Nonmetropolitan Territory of New Zealand	Elisabeth II	583	CK
CRI	Costa Rica	North America	Central America	51100	1821	4023000	75.8000031	10226.00	9757.00	Costa Rica	Republic	Miguel Ángel Rodríguez Echeverría	584	CR
DJI	Djibouti	Africa	Eastern Africa	23200	1977	638000	50.7999992	382.00	373.00	Djibouti/Jibuti	Republic	Ismail Omar Guelleh	585	DJ
DMA	Dominica	North America	Caribbean	751	1978	71000	73.4000015	256.00	243.00	Dominica	Republic	Vernon Shaw	586	DM
DOM	Dominican Republic	North America	Caribbean	48511	1844	8495000	73.1999969	15846.00	15076.00	República Dominicana	Republic	Hipólito Mejía Domínguez	587	DO
ECU	Ecuador	South America	South America	283561	1822	12646000	71.0999985	19770.00	19769.00	Ecuador	Republic	Gustavo Noboa Bejarano	594	EC
EGY	Egypt	Africa	Northern Africa	1001449	1922	68470000	63.2999992	82710.00	75617.00	Misr	Republic	Hosni Mubarak	608	EG
SLV	El Salvador	North America	Central America	21041	1841	6276000	69.6999969	11863.00	11203.00	El Salvador	Republic	Francisco Guillermo Flores Pérez	645	SV
ERI	Eritrea	Africa	Eastern Africa	117600	1993	3850000	55.7999992	650.00	755.00	Ertra	Republic	Isayas Afewerki [Isaias Afwerki]	652	ER
ESP	Spain	Europe	Southern Europe	505992	1492	39441700	78.8000031	553233.00	532031.00	España	Constitutional Monarchy	Juan Carlos I	653	ES
ZAF	South Africa	Africa	Southern Africa	1221037	1910	40377000	51.0999985	116729.00	129092.00	South Africa	Republic	Thabo Mbeki	716	ZA
ETH	Ethiopia	Africa	Eastern Africa	1104300	-1000	62565000	45.2000008	6353.00	6180.00	YeItyop´iya	Republic	Negasso Gidada	756	ET
FLK	Falkland Islands	South America	South America	12173	\N	2000	\N	0.00	\N	Falkland Islands	Dependent Territory of the UK	Elisabeth II	763	FK
FJI	Fiji Islands	Oceania	Melanesia	18274	1970	817000	67.9000015	1536.00	2149.00	Fiji Islands	Republic	Josefa Iloilo	764	FJ
PHL	Philippines	Asia	Southeast Asia	300000	1946	75967000	67.5	65107.00	82239.00	Pilipinas	Republic	Gloria Macapagal-Arroyo	766	PH
FRO	Faroe Islands	Europe	Nordic Countries	1399	\N	43000	78.4000015	0.00	\N	Føroyar	Part of Denmark	Margrethe II	901	FO
GAB	Gabon	Africa	Central Africa	267668	1960	1226000	50.0999985	5493.00	5279.00	Le Gabon	Republic	Omar Bongo	902	GA
GMB	Gambia	Africa	Western Africa	11295	1965	1305000	53.2000008	320.00	325.00	The Gambia	Republic	Yahya Jammeh	904	GM
GEO	Georgia	Asia	Middle East	69700	1991	4968000	64.5	6064.00	5924.00	Sakartvelo	Republic	Eduard evardnadze	905	GE
GHA	Ghana	Africa	Western Africa	238533	1957	20212000	57.4000015	7137.00	6884.00	Ghana	Republic	John Kufuor	910	GH
GIB	Gibraltar	Europe	Southern Europe	6	\N	25000	79	258.00	\N	Gibraltar	Dependent Territory of the UK	Elisabeth II	915	GI
GRD	Grenada	North America	Caribbean	344	1974	94000	64.5	318.00	\N	Grenada	Constitutional Monarchy	Elisabeth II	916	GD
GRL	Greenland	North America	North America	2166090	\N	56000	68.0999985	0.00	\N	Kalaallit Nunaat/Grønland	Part of Denmark	Margrethe II	917	GL
GLP	Guadeloupe	North America	Caribbean	1705	\N	456000	77	3501.00	\N	Guadeloupe	Overseas Department of France	Jacques Chirac	919	GP
GUM	Guam	Oceania	Micronesia	549	\N	168000	77.8000031	1197.00	1136.00	Guam	US Territory	George W. Bush	921	GU
GTM	Guatemala	North America	Central America	108889	1821	11385000	66.1999969	19008.00	17797.00	Guatemala	Republic	Alfonso Portillo Cabrera	922	GT
GIN	Guinea	Africa	Western Africa	245857	1958	7430000	45.5999985	2352.00	2383.00	Guinée	Republic	Lansana Conté	926	GN
GNB	Guinea-Bissau	Africa	Western Africa	36125	1974	1213000	49	293.00	272.00	Guiné-Bissau	Republic	Kumba Ialá	927	GW
GUY	Guyana	South America	South America	214969	1966	861000	64	722.00	743.00	Guyana	Republic	Bharrat Jagdeo	928	GY
HTI	Haiti	North America	Caribbean	27750	1804	8222000	49.2000008	3459.00	3107.00	Haïti/Dayti	Republic	Jean-Bertrand Aristide	929	HT
HND	Honduras	North America	Central America	112088	1838	6485000	69.9000015	5333.00	4697.00	Honduras	Republic	Carlos Roberto Flores Facussé	933	HN
HKG	Hong Kong	Asia	Eastern Asia	1075	\N	6782000	79.5	166448.00	173610.00	Xianggang/Hong Kong	Special Administrative Region of China	Jiang Zemin	937	HK
SJM	Svalbard and Jan Mayen	Europe	Nordic Countries	62422	\N	3200	\N	0.00	\N	Svalbard og Jan Mayen	Dependent Territory of Norway	Harald V	938	SJ
IDN	Indonesia	Asia	Southeast Asia	1904569	1945	212107000	68	84982.00	215002.00	Indonesia	Republic	Abdurrahman Wahid	939	ID
IND	India	Asia	Southern and Central Asia	3287263	1947	1013662000	62.5	447114.00	430572.00	Bharat/India	Federal Republic	Kocheril Raman Narayanan	1109	IN
IRQ	Iraq	Asia	Middle East	438317	1932	23115000	66.5	11500.00	\N	Al-´Iraq	Republic	Saddam Hussein al-Takriti	1365	IQ
IRN	Iran	Asia	Southern and Central Asia	1648195	1906	67702000	69.6999969	195746.00	160151.00	Iran	Islamic Republic	Ali Mohammad Khatami-Ardakani	1380	IR
IRL	Ireland	Europe	British Islands	70273	1921	3775100	76.8000031	75921.00	73132.00	Ireland/Éire	Republic	Mary McAleese	1447	IE
ISL	Iceland	Europe	Nordic Countries	103000	1944	279000	79.4000015	8255.00	7474.00	Ísland	Republic	Ólafur Ragnar Grímsson	1449	IS
ISR	Israel	Asia	Middle East	21056	1948	6217000	78.5999985	97477.00	98577.00	Yisrael/Israil	Republic	Moshe Katzav	1450	IL
ITA	Italy	Europe	Southern Europe	301316	1861	57680000	79	1161755.00	1145372.00	Italia	Republic	Carlo Azeglio Ciampi	1464	IT
TMP	East Timor	Asia	Southeast Asia	14874	\N	885000	46	0.00	\N	Timor Timur	Administrated by the UN	José Alexandre Gusmão	1522	TP
AUT	Austria	Europe	Western Europe	83859	1918	8091800	77.6999969	211860.00	206025.00	Österreich	Federal Republic	Thomas Klestil	1523	AT
JAM	Jamaica	North America	Caribbean	10990	1962	2583000	75.1999969	6871.00	6722.00	Jamaica	Constitutional Monarchy	Elisabeth II	1530	JM
JPN	Japan	Asia	Eastern Asia	377829	-660	126714000	80.6999969	3787042.00	4192638.00	Nihon/Nippon	Constitutional Monarchy	Akihito	1532	JP
YEM	Yemen	Asia	Middle East	527968	1918	18112000	59.7999992	6041.00	5729.00	Al-Yaman	Republic	Ali Abdallah Salih	1780	YE
JOR	Jordan	Asia	Middle East	88946	1946	5083000	77.4000015	7526.00	7051.00	Al-Urdunn	Constitutional Monarchy	Abdullah II	1786	JO
CXR	Christmas Island	Oceania	Australia and New Zealand	135	\N	2500	\N	0.00	\N	Christmas Island	Territory of Australia	Elisabeth II	1791	CX
YUG	Yugoslavia	Europe	Southern Europe	102173	1918	10640000	72.4000015	17000.00	\N	Jugoslavija	Federal Republic	Vojislav Kotunica	1792	YU
KHM	Cambodia	Asia	Southeast Asia	181035	1953	11168000	56.5	5121.00	5670.00	Kâmpuchéa	Constitutional Monarchy	Norodom Sihanouk	1800	KH
CMR	Cameroon	Africa	Central Africa	475442	1960	15085000	54.7999992	9174.00	8596.00	Cameroun/Cameroon	Republic	Paul Biya	1804	CM
CAN	Canada	North America	North America	9970610	1867	31147000	79.4000015	598862.00	625626.00	Canada	Constitutional Monarchy, Federation	Elisabeth II	1822	CA
CPV	Cape Verde	Africa	Western Africa	4033	1975	428000	68.9000015	435.00	420.00	Cabo Verde	Republic	António Mascarenhas Monteiro	1859	CV
KAZ	Kazakstan	Asia	Southern and Central Asia	2724900	1991	16223000	63.2000008	24375.00	23383.00	Qazaqstan	Republic	Nursultan Nazarbajev	1864	KZ
KEN	Kenya	Africa	Eastern Africa	580367	1963	30080000	48	9217.00	10241.00	Kenya	Republic	Daniel arap Moi	1881	KE
CAF	Central African Republic	Africa	Central Africa	622984	1960	3615000	44	1054.00	993.00	Centrafrique/Bê-Afrîka	Republic	Ange-Félix Patassé	1889	CF
CHN	China	Asia	Eastern Asia	9572900	-1523	1277558000	71.4000015	982268.00	917719.00	Zhongquo	People'sRepublic	Jiang Zemin	1891	CN
KGZ	Kyrgyzstan	Asia	Southern and Central Asia	199900	1991	4699000	63.4000015	1626.00	1767.00	Kyrgyzstan	Republic	Askar Akajev	2253	KG
KIR	Kiribati	Oceania	Micronesia	726	1979	83000	59.7999992	40.70	\N	Kiribati	Republic	Teburoro Tito	2256	KI
COL	Colombia	South America	South America	1138914	1810	42321000	70.3000031	102896.00	105116.00	Colombia	Republic	Andrés Pastrana Arango	2257	CO
COM	Comoros	Africa	Eastern Africa	1862	1975	578000	60	4401.00	4361.00	Komori/Comores	Republic	Azali Assoumani	2295	KM
COG	Congo	Africa	Central Africa	342000	1960	2943000	47.4000015	2108.00	2287.00	Congo	Republic	Denis Sassou-Nguesso	2296	CG
COD	Congo, The Democratic Republic of the	Africa	Central Africa	2344858	1960	51654000	48.7999992	6964.00	2474.00	République Démocratique du Congo	Republic	Joseph Kabila	2298	CD
CCK	Cocos (Keeling) Islands	Oceania	Australia and New Zealand	14	\N	600	\N	0.00	\N	Cocos (Keeling) Islands	Territory of Australia	Elisabeth II	2317	CC
PRK	North Korea	Asia	Eastern Asia	120538	1948	24039000	70.6999969	5332.00	\N	Choson Minjujuui In´min Konghwaguk (Bukhan)	Socialistic Republic	Kim Jong-il	2318	KP
KOR	South Korea	Asia	Eastern Asia	99434	1948	46844000	74.4000015	320749.00	442544.00	Taehan Minguk (Namhan)	Republic	Kim Dae-jung	2331	KR
GRC	Greece	Europe	Southern Europe	131626	1830	10545700	78.4000015	120724.00	119946.00	Elláda	Republic	Kostis Stefanopoulos	2401	GR
HRV	Croatia	Europe	Southern Europe	56538	1991	4473000	73.6999969	20208.00	19300.00	Hrvatska	Republic	tipe Mesic	2409	HR
CUB	Cuba	North America	Caribbean	110861	1902	11201000	76.1999969	17843.00	18862.00	Cuba	Socialistic Republic	Fidel Castro Ruz	2413	CU
KWT	Kuwait	Asia	Middle East	17818	1961	1972000	76.0999985	27037.00	30373.00	Al-Kuwayt	Constitutional Monarchy (Emirate)	Jabir al-Ahmad al-Jabir al-Sabah	2429	KW
CYP	Cyprus	Asia	Middle East	9251	1960	754700	76.6999969	9333.00	8246.00	Kýpros/Kibris	Republic	Glafkos Klerides	2430	CY
LAO	Laos	Asia	Southeast Asia	236800	1953	5433000	53.0999985	1292.00	1746.00	Lao	Republic	Khamtay Siphandone	2432	LA
LVA	Latvia	Europe	Baltic Countries	64589	1991	2424200	68.4000015	6398.00	5639.00	Latvija	Republic	Vaira Vike-Freiberga	2434	LV
LSO	Lesotho	Africa	Southern Africa	30355	1966	2153000	50.7999992	1061.00	1161.00	Lesotho	Constitutional Monarchy	Letsie III	2437	LS
LBN	Lebanon	Asia	Middle East	10400	1941	3282000	71.3000031	17121.00	15129.00	Lubnan	Republic	Émile Lahoud	2438	LB
LBR	Liberia	Africa	Western Africa	111369	1847	3154000	51	2012.00	\N	Liberia	Republic	Charles Taylor	2440	LR
LBY	Libyan Arab Jamahiriya	Africa	Northern Africa	1759540	1951	5605000	75.5	44806.00	40562.00	Libiya	Socialistic State	Muammar al-Qadhafi	2441	LY
LIE	Liechtenstein	Europe	Western Europe	160	1806	32300	78.8000031	1119.00	1084.00	Liechtenstein	Constitutional Monarchy	Hans-Adam II	2446	LI
LTU	Lithuania	Europe	Baltic Countries	65301	1991	3698500	69.0999985	10692.00	9585.00	Lietuva	Republic	Valdas Adamkus	2447	LT
LUX	Luxembourg	Europe	Western Europe	2586	1867	435700	77.0999985	16321.00	15519.00	Luxembourg/Lëtzebuerg	Constitutional Monarchy	Henri	2452	LU
ESH	Western Sahara	Africa	Northern Africa	266000	\N	293000	49.7999992	60.00	\N	As-Sahrawiya	Occupied by Marocco	Mohammed Abdel Aziz	2453	EH
MAC	Macao	Asia	Eastern Asia	18	\N	473000	81.5999985	5749.00	5940.00	Macau/Aomen	Special Administrative Region of China	Jiang Zemin	2454	MO
MDG	Madagascar	Africa	Eastern Africa	587041	1960	15942000	55	3750.00	3545.00	Madagasikara/Madagascar	Federal Republic	Didier Ratsiraka	2455	MG
MKD	Macedonia	Europe	Southern Europe	25713	1991	2024000	73.8000031	1694.00	1915.00	Makedonija	Republic	Boris Trajkovski	2460	MK
MWI	Malawi	Africa	Eastern Africa	118484	1964	10925000	37.5999985	1687.00	2527.00	Malawi	Republic	Bakili Muluzi	2462	MW
MDV	Maldives	Asia	Southern and Central Asia	298	1965	286000	62.2000008	199.00	\N	Dhivehi Raajje/Maldives	Republic	Maumoon Abdul Gayoom	2463	MV
MYS	Malaysia	Asia	Southeast Asia	329758	1957	22244000	70.8000031	69213.00	97884.00	Malaysia	Constitutional Monarchy, Federation	Salahuddin Abdul Aziz Shah Alhaj	2464	MY
MLI	Mali	Africa	Western Africa	1240192	1960	11234000	46.7000008	2642.00	2453.00	Mali	Republic	Alpha Oumar Konaré	2482	ML
MLT	Malta	Europe	Southern Europe	316	1964	380200	77.9000015	3512.00	3338.00	Malta	Republic	Guido de Marco	2484	MT
MAR	Morocco	Africa	Northern Africa	446550	1956	28351000	69.0999985	36124.00	33514.00	Al-Maghrib	Constitutional Monarchy	Mohammed VI	2486	MA
MHL	Marshall Islands	Oceania	Micronesia	181	1990	64000	65.5	97.00	\N	Marshall Islands/Majol	Republic	Kessai Note	2507	MH
MTQ	Martinique	North America	Caribbean	1102	\N	395000	78.3000031	2731.00	2559.00	Martinique	Overseas Department of France	Jacques Chirac	2508	MQ
MRT	Mauritania	Africa	Western Africa	1025520	1960	2670000	50.7999992	998.00	1081.00	Muritaniya/Mauritanie	Republic	Maaouiya Ould Sid´Ahmad Taya	2509	MR
MUS	Mauritius	Africa	Eastern Africa	2040	1968	1158000	71	4251.00	4186.00	Mauritius	Republic	Cassam Uteem	2511	MU
MYT	Mayotte	Africa	Eastern Africa	373	\N	149000	59.5	0.00	\N	Mayotte	Territorial Collectivity of France	Jacques Chirac	2514	YT
MEX	Mexico	North America	Central America	1958201	1810	98881000	71.5	414972.00	401461.00	México	Federal Republic	Vicente Fox Quesada	2515	MX
FSM	Micronesia, Federated States of	Oceania	Micronesia	702	1990	119000	68.5999985	212.00	\N	Micronesia	Federal Republic	Leo A. Falcam	2689	FM
MDA	Moldova	Europe	Eastern Europe	33851	1991	4380000	64.5	1579.00	1872.00	Moldova	Republic	Vladimir Voronin	2690	MD
MCO	Monaco	Europe	Western Europe	1.5	1861	34000	78.8000031	776.00	\N	Monaco	Constitutional Monarchy	Rainier III	2695	MC
MNG	Mongolia	Asia	Eastern Asia	1566500	1921	2662000	67.3000031	1043.00	933.00	Mongol Uls	Republic	Natsagiin Bagabandi	2696	MN
MSR	Montserrat	North America	Caribbean	102	\N	11000	78	109.00	\N	Montserrat	Dependent Territory of the UK	Elisabeth II	2697	MS
MOZ	Mozambique	Africa	Eastern Africa	801590	1975	19680000	37.5	2891.00	2711.00	Moçambique	Republic	Joaquím A. Chissano	2698	MZ
MMR	Myanmar	Asia	Southeast Asia	676578	1948	45611000	54.9000015	180375.00	171028.00	Myanma Pye	Republic	kenraali Than Shwe	2710	MM
NAM	Namibia	Africa	Southern Africa	824292	1990	1726000	42.5	3101.00	3384.00	Namibia	Republic	Sam Nujoma	2726	NA
NRU	Nauru	Oceania	Micronesia	21	1968	12000	60.7999992	197.00	\N	Naoero/Nauru	Republic	Bernard Dowiyogo	2728	NR
NPL	Nepal	Asia	Southern and Central Asia	147181	1769	23930000	57.7999992	4768.00	4837.00	Nepal	Constitutional Monarchy	Gyanendra Bir Bikram	2729	NP
NIC	Nicaragua	North America	Central America	130000	1838	5074000	68.6999969	1988.00	2023.00	Nicaragua	Republic	Arnoldo Alemán Lacayo	2734	NI
NER	Niger	Africa	Western Africa	1267000	1960	10730000	41.2999992	1706.00	1580.00	Niger	Republic	Mamadou Tandja	2738	NE
NGA	Nigeria	Africa	Western Africa	923768	1960	111506000	51.5999985	65707.00	58623.00	Nigeria	Federal Republic	Olusegun Obasanjo	2754	NG
NIU	Niue	Oceania	Polynesia	260	\N	2000	\N	0.00	\N	Niue	Nonmetropolitan Territory of New Zealand	Elisabeth II	2805	NU
NFK	Norfolk Island	Oceania	Australia and New Zealand	36	\N	2000	\N	0.00	\N	Norfolk Island	Territory of Australia	Elisabeth II	2806	NF
NOR	Norway	Europe	Nordic Countries	323877	1905	4478500	78.6999969	145895.00	153370.00	Norge	Constitutional Monarchy	Harald V	2807	NO
CIV	Côte dIvoire	Africa	Western Africa	322463	1960	14786000	45.2000008	11345.00	10285.00	Côte dIvoire	Republic	Laurent Gbagbo	2814	CI
OMN	Oman	Asia	Middle East	309500	1951	2542000	71.8000031	16904.00	16153.00	´Uman	Monarchy (Sultanate)	Qabus ibn Sa´id	2821	OM
PAK	Pakistan	Asia	Southern and Central Asia	796095	1947	156483000	61.0999985	61289.00	58549.00	Pakistan	Republic	Mohammad Rafiq Tarar	2831	PK
PLW	Palau	Oceania	Micronesia	459	1994	19000	68.5999985	105.00	\N	Belau/Palau	Republic	Kuniwo Nakamura	2881	PW
PAN	Panama	North America	Central America	75517	1903	2856000	75.5	9131.00	8700.00	Panamá	Republic	Mireya Elisa Moscoso Rodríguez	2882	PA
PNG	Papua New Guinea	Oceania	Melanesia	462840	1975	4807000	63.0999985	4988.00	6328.00	Papua New Guinea/Papua Niugini	Constitutional Monarchy	Elisabeth II	2884	PG
PRY	Paraguay	South America	South America	406752	1811	5496000	73.6999969	8444.00	9555.00	Paraguay	Republic	Luis Ángel González Macchi	2885	PY
PER	Peru	South America	South America	1285216	1821	25662000	70	64140.00	65186.00	Perú/Piruw	Republic	Valentin Paniagua Corazao	2890	PE
PCN	Pitcairn	Oceania	Polynesia	49	\N	50	\N	0.00	\N	Pitcairn	Dependent Territory of the UK	Elisabeth II	2912	PN
MNP	Northern Mariana Islands	Oceania	Micronesia	464	\N	78000	75.5	0.00	\N	Northern Mariana Islands	Commonwealth of the US	George W. Bush	2913	MP
PRT	Portugal	Europe	Southern Europe	91982	1143	9997600	75.8000031	105954.00	102133.00	Portugal	Republic	Jorge Sampãio	2914	PT
PRI	Puerto Rico	North America	Caribbean	8875	\N	3869000	75.5999985	34100.00	32100.00	Puerto Rico	Commonwealth of the US	George W. Bush	2919	PR
POL	Poland	Europe	Eastern Europe	323250	1918	38653600	73.1999969	151697.00	135636.00	Polska	Republic	Aleksander Kwasniewski	2928	PL
GNQ	Equatorial Guinea	Africa	Central Africa	28051	1968	453000	53.5999985	283.00	542.00	Guinea Ecuatorial	Republic	Teodoro Obiang Nguema Mbasogo	2972	GQ
QAT	Qatar	Asia	Middle East	11000	1971	599000	72.4000015	9472.00	8920.00	Qatar	Monarchy	Hamad ibn Khalifa al-Thani	2973	QA
FRA	France	Europe	Western Europe	551500	843	59225700	78.8000031	1424285.00	1392448.00	France	Republic	Jacques Chirac	2974	FR
GUF	French Guiana	South America	South America	90000	\N	181000	76.0999985	681.00	\N	Guyane française	Overseas Department of France	Jacques Chirac	3014	GF
PYF	French Polynesia	Oceania	Polynesia	4000	\N	235000	74.8000031	818.00	781.00	Polynésie française	Nonmetropolitan Territory of France	Jacques Chirac	3016	PF
REU	Réunion	Africa	Eastern Africa	2510	\N	699000	72.6999969	8287.00	7988.00	Réunion	Overseas Department of France	Jacques Chirac	3017	RE
ROM	Romania	Europe	Eastern Europe	238391	1878	22455500	69.9000015	38158.00	34843.00	România	Republic	Ion Iliescu	3018	RO
RWA	Rwanda	Africa	Eastern Africa	26338	1962	7733000	39.2999992	2036.00	1863.00	Rwanda/Urwanda	Republic	Paul Kagame	3047	RW
SWE	Sweden	Europe	Nordic Countries	449964	836	8861400	79.5999985	226492.00	227757.00	Sverige	Constitutional Monarchy	Carl XVI Gustaf	3048	SE
SHN	Saint Helena	Africa	Western Africa	314	\N	6000	76.8000031	0.00	\N	Saint Helena	Dependent Territory of the UK	Elisabeth II	3063	SH
KNA	Saint Kitts and Nevis	North America	Caribbean	261	1983	38000	70.6999969	299.00	\N	Saint Kitts and Nevis	Constitutional Monarchy	Elisabeth II	3064	KN
LCA	Saint Lucia	North America	Caribbean	622	1979	154000	72.3000031	571.00	\N	Saint Lucia	Constitutional Monarchy	Elisabeth II	3065	LC
VCT	Saint Vincent and the Grenadines	North America	Caribbean	388	1979	114000	72.3000031	285.00	\N	Saint Vincent and the Grenadines	Constitutional Monarchy	Elisabeth II	3066	VC
SPM	Saint Pierre and Miquelon	North America	North America	242	\N	7000	77.5999985	0.00	\N	Saint-Pierre-et-Miquelon	Territorial Collectivity of France	Jacques Chirac	3067	PM
DEU	Germany	Europe	Western Europe	357022	1955	82164700	77.4000015	2133367.00	2102826.00	Deutschland	Federal Republic	Johannes Rau	3068	DE
SLB	Solomon Islands	Oceania	Melanesia	28896	1978	444000	71.3000031	182.00	220.00	Solomon Islands	Constitutional Monarchy	Elisabeth II	3161	SB
ZMB	Zambia	Africa	Eastern Africa	752618	1964	9169000	37.2000008	3377.00	3922.00	Zambia	Republic	Frederick Chiluba	3162	ZM
WSM	Samoa	Oceania	Polynesia	2831	1962	180000	69.1999969	141.00	157.00	Samoa	Parlementary Monarchy	Malietoa Tanumafili II	3169	WS
SMR	San Marino	Europe	Southern Europe	61	885	27000	81.0999985	510.00	\N	San Marino	Republic	\N	3171	SM
STP	Sao Tome and Principe	Africa	Central Africa	964	1975	147000	65.3000031	6.00	\N	São Tomé e Príncipe	Republic	Miguel Trovoada	3172	ST
SAU	Saudi Arabia	Asia	Middle East	2149690	1932	21607000	67.8000031	137635.00	146171.00	Al-´Arabiya as-Sa´udiya	Monarchy	Fahd ibn Abdul-Aziz al-Sa´ud	3173	SA
SEN	Senegal	Africa	Western Africa	196722	1960	9481000	62.2000008	4787.00	4542.00	Sénégal/Sounougal	Republic	Abdoulaye Wade	3198	SN
SYC	Seychelles	Africa	Eastern Africa	455	1976	77000	70.4000015	536.00	539.00	Sesel/Seychelles	Republic	France-Albert René	3206	SC
SLE	Sierra Leone	Africa	Western Africa	71740	1961	4854000	45.2999992	746.00	858.00	Sierra Leone	Republic	Ahmed Tejan Kabbah	3207	SL
SGP	Singapore	Asia	Southeast Asia	618	1965	3567000	80.0999985	86503.00	96318.00	Singapore/Singapura/Xinjiapo/Singapur	Republic	Sellapan Rama Nathan	3208	SG
SVK	Slovakia	Europe	Eastern Europe	49012	1993	5398700	73.6999969	20594.00	19452.00	Slovensko	Republic	Rudolf Schuster	3209	SK
SVN	Slovenia	Europe	Southern Europe	20256	1991	1987800	74.9000015	19756.00	18202.00	Slovenija	Republic	Milan Kucan	3212	SI
SOM	Somalia	Africa	Eastern Africa	637657	1960	10097000	46.2000008	935.00	\N	Soomaaliya	Republic	Abdiqassim Salad Hassan	3214	SO
LKA	Sri Lanka	Asia	Southern and Central Asia	65610	1948	18827000	71.8000031	15706.00	15091.00	Sri Lanka/Ilankai	Republic	Chandrika Kumaratunga	3217	LK
SDN	Sudan	Africa	Northern Africa	2505813	1956	29490000	56.5999985	10162.00	\N	As-Sudan	Islamic Republic	Omar Hassan Ahmad al-Bashir	3225	SD
FIN	Finland	Europe	Nordic Countries	338145	1917	5171300	77.4000015	121914.00	119833.00	Suomi	Republic	Tarja Halonen	3236	FI
SUR	Suriname	South America	South America	163265	1975	417000	71.4000015	870.00	706.00	Suriname	Republic	Ronald Venetiaan	3243	SR
SWZ	Swaziland	Africa	Southern Africa	17364	1968	1008000	40.4000015	1206.00	1312.00	kaNgwane	Monarchy	Mswati III	3244	SZ
CHE	Switzerland	Europe	Western Europe	41284	1499	7160400	79.5999985	264478.00	256092.00	Schweiz/Suisse/Svizzera/Svizra	Federation	Adolf Ogi	3248	CH
SYR	Syria	Asia	Middle East	185180	1941	16125000	68.5	65984.00	64926.00	Suriya	Republic	Bashar al-Assad	3250	SY
TJK	Tajikistan	Asia	Southern and Central Asia	143100	1991	6188000	64.0999985	1990.00	1056.00	Toçikiston	Republic	Emomali Rahmonov	3261	TJ
TWN	Taiwan	Asia	Eastern Asia	36188	1945	22256000	76.4000015	256254.00	263451.00	Tai-wan	Republic	Chen Shui-bian	3263	TW
TZA	Tanzania	Africa	Eastern Africa	883749	1961	33517000	52.2999992	8005.00	7388.00	Tanzania	Republic	Benjamin William Mkapa	3306	TZ
DNK	Denmark	Europe	Nordic Countries	43094	800	5330000	76.5	174099.00	169264.00	Danmark	Constitutional Monarchy	Margrethe II	3315	DK
THA	Thailand	Asia	Southeast Asia	513115	1350	61399000	68.5999985	116416.00	153907.00	Prathet Thai	Constitutional Monarchy	Bhumibol Adulyadej	3320	TH
TGO	Togo	Africa	Western Africa	56785	1960	4629000	54.7000008	1449.00	1400.00	Togo	Republic	Gnassingbé Eyadéma	3332	TG
TKL	Tokelau	Oceania	Polynesia	12	\N	2000	\N	0.00	\N	Tokelau	Nonmetropolitan Territory of New Zealand	Elisabeth II	3333	TK
TON	Tonga	Oceania	Polynesia	650	1970	99000	67.9000015	146.00	170.00	Tonga	Monarchy	Taufa'ahau Tupou IV	3334	TO
TTO	Trinidad and Tobago	North America	Caribbean	5130	1962	1295000	68	6232.00	5867.00	Trinidad and Tobago	Republic	Arthur N. R. Robinson	3336	TT
TCD	Chad	Africa	Central Africa	1284000	1960	7651000	50.5	1208.00	1102.00	Tchad/Tshad	Republic	Idriss Déby	3337	TD
CZE	Czech Republic	Europe	Eastern Europe	78866	1993	10278100	74.5	55017.00	52037.00	¸esko	Republic	Václav Havel	3339	CZ
TUN	Tunisia	Africa	Northern Africa	163610	1956	9586000	73.6999969	20026.00	18898.00	Tunis/Tunisie	Republic	Zine al-Abidine Ben Ali	3349	TN
TUR	Turkey	Asia	Middle East	774815	1923	66591000	71	210721.00	189122.00	Türkiye	Republic	Ahmet Necdet Sezer	3358	TR
TKM	Turkmenistan	Asia	Southern and Central Asia	488100	1991	4459000	60.9000015	4397.00	2000.00	Türkmenostan	Republic	Saparmurad Nijazov	3419	TM
TCA	Turks and Caicos Islands	North America	Caribbean	430	\N	17000	73.3000031	96.00	\N	The Turks and Caicos Islands	Dependent Territory of the UK	Elisabeth II	3423	TC
TUV	Tuvalu	Oceania	Polynesia	26	1978	12000	66.3000031	6.00	\N	Tuvalu	Constitutional Monarchy	Elisabeth II	3424	TV
UGA	Uganda	Africa	Eastern Africa	241038	1962	21778000	42.9000015	6313.00	6887.00	Uganda	Republic	Yoweri Museveni	3425	UG
UKR	Ukraine	Europe	Eastern Europe	603700	1991	50456000	66	42168.00	49677.00	Ukrajina	Republic	Leonid Kutma	3426	UA
HUN	Hungary	Europe	Eastern Europe	93030	1918	10043200	71.4000015	48267.00	45914.00	Magyarország	Republic	Ferenc Mádl	3483	HU
URY	Uruguay	South America	South America	175016	1828	3337000	75.1999969	20831.00	19967.00	Uruguay	Republic	Jorge Batlle Ibáñez	3492	UY
NCL	New Caledonia	Oceania	Melanesia	18575	\N	214000	72.8000031	3563.00	\N	Nouvelle-Calédonie	Nonmetropolitan Territory of France	Jacques Chirac	3493	NC
NZL	New Zealand	Oceania	Australia and New Zealand	270534	1907	3862000	77.8000031	54669.00	64960.00	New Zealand/Aotearoa	Constitutional Monarchy	Elisabeth II	3499	NZ
UZB	Uzbekistan	Asia	Southern and Central Asia	447400	1991	24318000	63.7000008	14194.00	21300.00	Uzbekiston	Republic	Islam Karimov	3503	UZ
BLR	Belarus	Europe	Eastern Europe	207600	1991	10236000	68	13714.00	\N	Belarus	Republic	Aljaksandr Lukaenka	3520	BY
WLF	Wallis and Futuna	Oceania	Polynesia	200	\N	15000	\N	0.00	\N	Wallis-et-Futuna	Nonmetropolitan Territory of France	Jacques Chirac	3536	WF
VUT	Vanuatu	Oceania	Melanesia	12189	1980	190000	60.5999985	261.00	246.00	Vanuatu	Republic	John Bani	3537	VU
VAT	Holy See (Vatican City State)	Europe	Southern Europe	0.400000006	1929	1000	\N	9.00	\N	Santa Sede/Città del Vaticano	Independent Church State	Johannes Paavali II	3538	VA
VEN	Venezuela	South America	South America	912050	1811	24170000	73.0999985	95023.00	88434.00	Venezuela	Federal Republic	Hugo Chávez Frías	3539	VE
RUS	Russian Federation	Europe	Eastern Europe	17075400	1991	146934000	67.1999969	276608.00	442989.00	Rossija	Federal Republic	Vladimir Putin	3580	RU
VNM	Vietnam	Asia	Southeast Asia	331689	1945	79832000	69.3000031	21929.00	22834.00	Viêt Nam	Socialistic Republic	Trân Duc Luong	3770	VN
EST	Estonia	Europe	Baltic Countries	45227	1991	1439200	69.5	5328.00	3371.00	Eesti	Republic	Lennart Meri	3791	EE
USA	United States	North America	North America	9363520	1776	278357000	77.0999985	8510700.00	8110900.00	United States	Federal Republic	George W. Bush	3813	US
VIR	Virgin Islands, U.S.	North America	Caribbean	347	\N	93000	78.0999985	0.00	\N	Virgin Islands of the United States	US Territory	George W. Bush	4067	VI
ZWE	Zimbabwe	Africa	Eastern Africa	390757	1980	11669000	37.7999992	5951.00	8670.00	Zimbabwe	Republic	Robert G. Mugabe	4068	ZW
PSE	Palestine	Asia	Middle East	6257	\N	3101000	71.4000015	4173.00	\N	Filastin	Autonomous Area	Yasser (Yasir) Arafat	4074	PS
ATA	Antarctica	Antarctica	Antarctica	13120000	\N	0	\N	0.00	\N		Co-administrated		\N	AQ
BVT	Bouvet Island	Antarctica	Antarctica	59	\N	0	\N	0.00	\N	Bouvetøya	Dependent Territory of Norway	Harald V	\N	BV
IOT	British Indian Ocean Territory	Africa	Eastern Africa	78	\N	0	\N	0.00	\N	British Indian Ocean Territory	Dependent Territory of the UK	Elisabeth II	\N	IO
SGS	South Georgia and the South Sandwich Islands	Antarctica	Antarctica	3903	\N	0	\N	0.00	\N	South Georgia and the South Sandwich Islands	Dependent Territory of the UK	Elisabeth II	\N	GS
HMD	Heard Island and McDonald Islands	Antarctica	Antarctica	359	\N	0	\N	0.00	\N	Heard and McDonald Islands	Territory of Australia	Elisabeth II	\N	HM
ATF	French Southern territories	Antarctica	Antarctica	7780	\N	0	\N	0.00	\N	Terres australes françaises	Nonmetropolitan Territory of France	Jacques Chirac	\N	TF
UMI	United States Minor Outlying Islands	Oceania	Micronesia/Caribbean	16	\N	0	\N	0.00	\N	United States Minor Outlying Islands	Dependent Territory of the US	George W. Bush	\N	UM
\.


--
-- Name: city_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY "t_city_cit"
    ADD CONSTRAINT city_pkey PRIMARY KEY (cit_id);


--
-- Name: country_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY "t_country_cou"
    ADD CONSTRAINT country_pkey PRIMARY KEY (cou_code);


--
-- Name: countrylanguage_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY "t_countrylanguage_col"
    ADD CONSTRAINT countrylanguage_pkey PRIMARY KEY (cou_code, col_language);


--
-- Name: country_capital_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "t_country_cou"
    ADD CONSTRAINT country_capital_fkey FOREIGN KEY (cou_capital) REFERENCES "t_city_cit"(cit_id);


--
-- Name: countrylanguage_countrycode_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY "t_countrylanguage_col"
    ADD CONSTRAINT countrylanguage_countrycode_fkey FOREIGN KEY (cou_code) REFERENCES "t_country_cou"(cou_code);


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

