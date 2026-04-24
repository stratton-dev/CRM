import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { api } from '@/api/client'
import type {
  User,
  UserRole,
  Rank,
  Client,
  SavedOffer,
  ClientActivity,
  Employee,
  Invoice,
  CommissionConfig,
  Notification,
  AuditLog,
  Email,
  KnowledgeFile,
  FileCategory,
  AutentiDocument,
} from '@/types/models'

const STORAGE_PREFIX = 'stratton_crm_'

function load<T>(key: string, defaultVal: T): T {
  try {
    const stored = localStorage.getItem(STORAGE_PREFIX + key)
    if (!stored) return defaultVal
    const parsed = JSON.parse(stored) as T
    if (Array.isArray(defaultVal) && !Array.isArray(parsed)) return defaultVal
    return parsed
  } catch (error) {
    console.error('LocalStorage load failed', error)
    return defaultVal
  }
}

function persist(key: string, value: unknown) {
  try {
    localStorage.setItem(STORAGE_PREFIX + key, JSON.stringify(value))
  } catch (error) {
    console.error('LocalStorage save failed', error)
  }
}

export const useDataStore = defineStore('data', () => {
  const auth = useAuthStore()
  const structurePassword = ref<string>(load('structurePassword', 'SP28'))

  const users = ref<User[]>(load('users', [
    { id: 'u1', email: 'admin@stratton.com', name: 'Super Admin', role: 'ADMIN', password: '123', phone: '000-000-000', rank: 'LEGEND', type: 'PRIVATE' },
    { id: 'u2', email: 'director@stratton.com', name: 'Testowy Dyrektor', role: 'DIRECTOR', password: '123', parentId: 'u1', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u3', email: 'manager@stratton.com', name: 'Testowy Manager', role: 'MANAGER', password: '123', parentId: 'u2', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u4', email: 'sales@stratton.com', name: 'Testowy Handlowiec', role: 'SALES', password: '123', parentId: 'u3', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u5', email: 'client@firma.pl', name: 'Anna Kadrowa', role: 'CLIENT_HR', password: '123', linkedClientId: 'c1', phone: '500-500-500', type: 'PRIVATE' },
    { id: 'u10', email: 'j.jablonski@stratton-prime.pl', name: 'Jakub Jabłoński', role: 'DIRECTOR', password: '123', phone: '604169970', hierarchicalId: 'GDJJ', contractStatus: 'SIGNED', parentId: 'u1', type: 'PRIVATE', points: 0, rank: 'JUNIOR', crmNumber: 'GDA/01/001' },
    { id: 'u11', email: 'j.adamczyk@stratton-prime.pl', name: 'Jarosław Adamczyk', role: 'MANAGER', password: '123', phone: '603544012', hierarchicalId: 'GDJJ/JA', contractStatus: 'SIGNED', parentId: 'u10', type: 'PRIVATE', points: 0, rank: 'JUNIOR', crmNumber: 'GDA/11/002' },
    { id: 'u12', email: 'u.dynowska@stratton-prime.pl', name: 'Urszula Dynowska', role: 'MANAGER', password: '123', phone: '500245833', hierarchicalId: 'GDJJ/JA/UD', contractStatus: 'SIGNED', parentId: 'u11', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u13', email: 'm.sienkiewicz@stratton-prime.pl', name: 'Malgorzata Sienkiewicz-Łuczyn', role: 'MANAGER', password: '123', phone: '536536917', hierarchicalId: 'GDJJ/JA/UD/MS', contractStatus: 'SIGNED', parentId: 'u12', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u14', email: 'j.choroszynski@stratton-prime.pl', name: 'Rafał Choroszynski', role: 'MANAGER', password: '123', phone: '517031335', hierarchicalId: 'GDJJ/JA/RC', contractStatus: 'SIGNED', parentId: 'u11', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u15', email: 'p.szerniewicz@stratton-prime.pl', name: 'Patryk Szerniewicz', role: 'SALES', password: '123', phone: '577323775', hierarchicalId: 'GDJJ/JA/RC/PS', contractStatus: 'SIGNED', parentId: 'u14', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u16', email: 'p.pstrocki@stratton-prime.pl', name: 'Piotr Pstrocki', role: 'SALES', password: '123', phone: '533528439', hierarchicalId: 'GDJJ/JA/RC/PP', contractStatus: 'SIGNED', parentId: 'u14', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u17', email: 'j.lisowska@stratton-prime.pl', name: 'Joanna (Darek) Lisowska', role: 'SALES', password: '123', phone: '535941380', hierarchicalId: 'GDJJ/JA/RC/JL', contractStatus: 'SIGNED', parentId: 'u14', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u18', email: 'a.lietz@stratton-prime.pl', name: 'Alicja Lietz', role: 'SALES', password: '123', phone: '881764650', hierarchicalId: 'GDJJ/JA/AL', contractStatus: 'SIGNED', parentId: 'u11', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u19', email: 'natalia.kvk@stratton-prime.pl', name: 'Natalia Kropez vel Kropacz', role: 'MANAGER', password: '123', phone: '513036766', hierarchicalId: 'GDJJ/NK', contractStatus: 'SIGNED', parentId: 'u10', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u20', email: 'k.jackiewicz@stratton-prime.pl', name: 'Karol Jackiewicz', role: 'SALES', password: '123', phone: '790221468', hierarchicalId: 'GDJJ/NK/KJ', contractStatus: 'SIGNED', parentId: 'u19', type: 'PRIVATE', points: 0, rank: 'JUNIOR', crmNumber: 'GDA/20/003' },
    { id: 'u21', email: 'k.rimpel@stratton-prime.pl', name: 'Krzysztof Rimpel', role: 'SALES', password: '123', phone: '451506843', hierarchicalId: 'GDJJ/NK/KR', contractStatus: 'SIGNED', parentId: 'u19', type: 'PRIVATE', points: 0, rank: 'JUNIOR', crmNumber: 'GDA/21/004' },
    { id: 'u22', email: 'd.orzechowski@stratton-prime.pl', name: 'Daniel Orzechowski', role: 'SALES', password: '123', phone: '530262587', hierarchicalId: 'GDJJ/JA/UD/MS/DO', contractStatus: 'DRAFT', parentId: 'u13', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u23', email: 'j.wojtyra@stratton-prime.pl', name: 'Jacek Wojtyra', role: 'SALES', password: '123', phone: '510517273', hierarchicalId: 'GDJJ/NK/JW', contractStatus: 'SIGNED', parentId: 'u19', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u24', email: 'm.tyl@stratton-prime.pl', name: 'Mateusz Tyl', role: 'MANAGER', password: '123', phone: '782565487', hierarchicalId: 'GDJJ/MT', contractStatus: 'SIGNED', parentId: 'u10', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u25', email: 'p.blaszkowski@stratton-prime.pl', name: 'Paweł błaszkowski', role: 'SALES', password: '123', phone: '574713696', hierarchicalId: 'GDJJ/MT/PB', contractStatus: 'SIGNED', parentId: 'u24', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u26', email: 'm.kowalska@stratton-prime.pl', name: 'Magdalena Kowalska', role: 'SALES', password: '123', phone: '500471728', hierarchicalId: 'GDJJ/MT/MK', contractStatus: 'SIGNED', parentId: 'u24', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u27', email: 't.lasowski@stratton-prime.pl', name: 'Tomasz Lasowski', role: 'MANAGER', password: '123', phone: '793093867', hierarchicalId: 'GDJJ/TL', contractStatus: 'SIGNED', parentId: 'u10', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u28', email: 'm.jablonski@stratton-prime.pl', name: 'Maciej Jabłoński', role: 'MANAGER', password: '123', phone: '602638884', hierarchicalId: 'GDJJ/MJ', contractStatus: 'SIGNED', parentId: 'u10', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u29', email: 't.pawlikowski@stratton-prime.pl', name: 'Tymoteusz Pawlikowski', role: 'MANAGER', password: '123', phone: '600156719', hierarchicalId: 'GDJJ/TP', contractStatus: 'SIGNED', parentId: 'u10', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u30', email: 'm.yakymchuk@stratton-prime.pl', name: 'Maksym Yakymchuk', role: 'SALES', password: '123', phone: '889139579', hierarchicalId: 'GDJJ/TP/MY', contractStatus: 'SIGNED', parentId: 'u29', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u31', email: 'biuro@wordpress2592813.home.pl', name: 'Szczepan Dziwosz (str. KIELCE)', role: 'MANAGER', password: '123', phone: '888346165', hierarchicalId: 'GDJJ/SK', contractStatus: 'SIGNED', parentId: 'u10', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u32', email: 'k.adach@stratton-prime.pl', name: 'Konrad Adach', role: 'SALES', password: '123', phone: '518565560', hierarchicalId: 'GDJJ/SK/KA', contractStatus: 'SIGNED', parentId: 'u31', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u33', email: 'a.grabowska@stratton-prime.pl', name: 'Aleksandra Grabowska', role: 'MANAGER', password: '123', phone: '796123868', hierarchicalId: 'GDJJ/SK/AG', contractStatus: 'SIGNED', parentId: 'u31', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u34', email: 'd.raczkowska@stratton-prime.pl', name: 'Dominika Raczkowska', role: 'SALES', password: '123', phone: '733196327', hierarchicalId: 'GDJJ/SK/AG/DR', contractStatus: 'SIGNED', parentId: 'u33', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u35', email: 'o.zochowska@stratton-prime.pl', name: 'Olga Żochowska', role: 'SALES', password: '123', phone: '517624908', hierarchicalId: 'GDJJ/SK/AG/OZ', contractStatus: 'SIGNED', parentId: 'u33', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u36', email: 'm.kaczan@stratton-prime.pl', name: 'Maciej Kaczan', role: 'SALES', password: '123', phone: '797512031', hierarchicalId: 'GDJJ/SK/AG/MK', contractStatus: 'SIGNED', parentId: 'u33', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u37', email: 'p.wadynski@stratton.prime.pl', name: 'Paweł Wadyński', role: 'SALES', password: '123', phone: '601483372', hierarchicalId: 'GDJJ/SK/AG/PW', contractStatus: 'SIGNED', parentId: 'u33', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u38', email: 'k.kortas@stratton-prime.pl', name: 'Krzysztof Kortas', role: 'SALES', password: '123', phone: '508508499', hierarchicalId: 'GDJJ/KK', contractStatus: 'SIGNED', parentId: 'u10', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u39', email: 'k.galera@stratton-prime.pl', name: 'Karolina Galera', role: 'SALES', password: '123', phone: '888204035', hierarchicalId: 'GDJJ/JA/KG', contractStatus: 'SIGNED', parentId: 'u14', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u40', email: 'j.sowa@stratton-prime.pl', name: 'Jakub Sowa', role: 'SALES', password: '123', phone: '662363789', hierarchicalId: 'GDJJ/JA/JS', contractStatus: 'DRAFT', parentId: 'u11', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u41', email: 'k.renski@stratton-prime.pl', name: 'Kacper Reński', role: 'MANAGER', password: '123', phone: '509539625', hierarchicalId: 'GDJJ/KR', contractStatus: 'SIGNED', parentId: 'u10', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u42', email: 'h.witowski@stratton-prime.pl', name: 'Hubert Witowski', role: 'MANAGER', password: '123', phone: '530007530', hierarchicalId: 'GDJJ/TP/HW', contractStatus: 'DRAFT', parentId: 'u29', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u43', email: 'a.bal@stratton-prime.pl', name: 'Andrzej Bal', role: 'SALES', password: '123', phone: '734144609', hierarchicalId: 'GDJ/TP/HW/AB', contractStatus: 'SIGNED', parentId: 'u42', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u44', email: 'p.skalecki@stratton-prime.pl', name: 'Piotr Skałecki', role: 'SALES', password: '123', phone: '570475575', hierarchicalId: 'GDJJ/KR/PS', contractStatus: 'DRAFT', parentId: 'u41', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u45', email: 'p.lubinski@stratton-prime.pl', name: 'Paweł Lubiński', role: 'SALES', password: '123', phone: '780059690', hierarchicalId: 'GDJJ/KR/PL', contractStatus: 'SIGNED', parentId: 'u41', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u46', email: 'p.wolski@stratton-prime.pl', name: 'Przemysław Wolski', role: 'SALES', password: '123', phone: '501183234', hierarchicalId: 'GDJJ/KR/PW', contractStatus: 'SIGNED', parentId: 'u41', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u47', email: 'p.glogowski@stratton-prime.pl', name: 'Piotr Głogowski', role: 'SALES', password: '123', phone: '531919914', hierarchicalId: 'GDJJ/NK/PG', contractStatus: 'SIGNED', parentId: 'u19', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u48', email: 'l.szablewski@stratton-prime.pl', name: 'Łukasz Szablewski', role: 'SALES', password: '123', phone: '501161246', hierarchicalId: 'GDJJ/KR/LS', contractStatus: 'DRAFT', parentId: 'u41', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u49', email: 'r.wojtas@stratton-prime.pl', name: 'Rafał Wojtas', role: 'SALES', password: '123', phone: '666605053', hierarchicalId: 'GDJJ/JA/UD/MS/RW', contractStatus: 'SIGNED', parentId: 'u13', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u50', email: 'p.amanowicz@stratton-prime.pl', name: 'Piotr Amanowicz', role: 'SALES', password: '123', phone: '696164618', hierarchicalId: 'GDJJ/JA/UD/MS/PA', contractStatus: 'SIGNED', parentId: 'u13', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u51', email: 'm.cholewicka@stratton-prime.pl', name: 'Małgorzata Cholewicka', role: 'SALES', password: '123', phone: '793338666', hierarchicalId: 'GDJJ/JA/RC/MC', contractStatus: 'SIGNED', parentId: 'u14', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u52', email: 'a.wiczarska@stratton-prime.pl', name: 'Alina Wiczarska', role: 'SALES', password: '123', phone: '664722352', hierarchicalId: 'GDJJ/MT/AW', contractStatus: 'SIGNED', parentId: 'u24', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u53', email: 'm.szarolkiewicz@stratton-prime.pl', name: 'Marzanna Szarolkiewicz', role: 'MANAGER', password: '123', phone: '501600081', hierarchicalId: 'GDJJ/JA/UD/MS', contractStatus: 'SIGNED', parentId: 'u12', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u54', email: 'm.gwizdala@stratton-prime.pl', name: 'Mariola Gwizdała', role: 'MANAGER', password: '123', phone: '608496054', hierarchicalId: 'GDJJ/JA/UD/MG', contractStatus: 'SIGNED', parentId: 'u12', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u55', email: 'p.leonska@stratton-prime.pl', name: 'Paulina Leońska', role: 'SALES', password: '123', phone: '533973391', hierarchicalId: 'GDJJ/KR/PL', contractStatus: 'SIGNED', parentId: 'u41', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u56', email: 'a.seremak@stratton-prime.pl', name: 'Agnieszka Seremak', role: 'SALES', password: '123', phone: '534723222', hierarchicalId: 'GDJJ/KR/AS', contractStatus: 'DRAFT', parentId: 'u41', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u57', email: 'r.ogonowski@stratton-prime.pl', name: 'Robert Ogonowski', role: 'SALES', password: '123', phone: '600914000', hierarchicalId: 'GDJJ/JA/UD/MS/RO', contractStatus: 'SIGNED', parentId: 'u53', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u58', email: 'm.kwiatkowska@stratton-prime.pl', name: 'Martyna Kwiatkowska', role: 'SALES', password: '123', phone: '603188555', hierarchicalId: 'GDJJ/JA/UD/MS/MK', contractStatus: 'SIGNED', parentId: 'u53', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u59', email: 'm.skonieczka@stratton-prime.pl', name: 'Mikołaj Skonieczka', role: 'SALES', password: '123', phone: '783632630', hierarchicalId: 'GDJJ/NK/MS', contractStatus: 'SIGNED', parentId: 'u19', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u60', email: 'a.drywa@stratton-prime.pl', name: 'Anna Drywa', role: 'SALES', password: '123', phone: '733454909', hierarchicalId: 'GDJJ/JA/UD/MG/AD', contractStatus: 'SIGNED', parentId: 'u54', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u61', email: 'a.koszczyc@stratton-prime.pl', name: 'Aleksandra Koszczyc', role: 'SALES', password: '123', phone: '535002468', hierarchicalId: 'GDJJ/JA/UD/MG/AK', contractStatus: 'SIGNED', parentId: 'u54', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u62', email: 's.gralak@stratton-prime.pl', name: 'Sabrina Gralak', role: 'SALES', password: '123', phone: '603500397', hierarchicalId: 'GDJJ/JA/UD/MG/SG', contractStatus: 'SIGNED', parentId: 'u54', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u63', email: 'o.mielewczyk@stratton-prime.pl', name: 'Oliwia Mielewczyk', role: 'SALES', password: '123', phone: '533540300', hierarchicalId: 'GDJJ/JA/UD/MG/OM', contractStatus: 'SIGNED', parentId: 'u54', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u64', email: 'e.muttka@stratton-prime.pl', name: 'Ewelina Muttka', role: 'SALES', password: '123', phone: '453334039', hierarchicalId: 'GDJJ/JA/UD/MG', contractStatus: 'SIGNED', parentId: 'u54', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u65', email: 'a.quirini@stratton-prime.pl', name: 'Anna Quirini', role: 'SALES', password: '123', phone: '605564450', hierarchicalId: 'GDJJ/JA/UD/MG', contractStatus: 'SIGNED', parentId: 'u54', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u66', email: 'p.gwizdala@stratton-prime.pl', name: 'Patryk Gwizdała', role: 'SALES', password: '123', phone: '794009018', hierarchicalId: 'GDJJ/JA/UD/MG', contractStatus: 'SIGNED', parentId: 'u54', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u67', email: 'd.mironski@stratton-prime.pl', name: 'Dariusz Miroński', role: 'SALES', password: '123', phone: '502565306', hierarchicalId: 'GDJJ/JA/UD/MG', contractStatus: 'DRAFT', parentId: 'u54', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u68', email: 'n.malinowska@stratton-prime.pl', name: 'Natalia Malinowska', role: 'SALES', password: '123', phone: '533476452', hierarchicalId: 'GDJJ/JA/UD/MG', contractStatus: 'SIGNED', parentId: 'u54', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u69', email: 'k.grygierzec@stratton-prime.pl', name: 'Krzysztof Grygierzec', role: 'SALES', password: '123', phone: '882597190', hierarchicalId: 'GDJJ/JA/UD/MS/KG', contractStatus: 'SIGNED', parentId: 'u13', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u70', email: 'p.dymowski@stratton-prime.pl', name: 'Piotr Dymowski', role: 'SALES', password: '123', phone: '609497770', hierarchicalId: 'GDJJ/PD', contractStatus: 'SIGNED', parentId: 'u10', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u71', email: 'j.pawlowski@stratton-prime.pl', name: 'Jarosław Pawłowski', role: 'SALES', password: '123', phone: '507157741', hierarchicalId: 'GDJJ/JA/UD/MS/JP', contractStatus: 'SIGNED', parentId: 'u13', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u72', email: 'm.nizgorski@stratton-prime.pl', name: 'Michał Nizgórski', role: 'SALES', password: '123', phone: '888597097', hierarchicalId: 'GDJJ/JA/UD/MS/MN', contractStatus: 'SIGNED', parentId: 'u13', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u73', email: 'k.buczak@stratton-prime.pl', name: 'Kamil Buczak', role: 'SALES', password: '123', phone: '538390550', hierarchicalId: '', contractStatus: 'SIGNED', parentId: 'u10', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u74', email: 's.ivanchenko@stratton-prime.pl', name: 'Serchii Ivanchenko', role: 'SALES', password: '123', phone: '576005874', hierarchicalId: 'GDJJ/KK/SI', contractStatus: 'SIGNED', parentId: 'u38', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
    { id: 'u75', email: 'a.safader@stratton-prime.pl', name: 'Anna Safader-Szkudlarek', role: 'SALES', password: '123', phone: '517871271', hierarchicalId: 'WAAC/TM/AS', contractStatus: 'SIGNED', parentId: 'u10', type: 'PRIVATE', points: 0, rank: 'JUNIOR' },
  ]))

  const clients = ref<Client[]>(load('clients', [
    {
      id: 'c1', name: 'Test Corp Sp. z o.o.', nip: '1234567890', status: 'SIGNED',
      ownerId: 'u10', opiekunDisplay: 'Jakub Jabłoński (GDJJ)', contactName: 'Adam Nowak', contactPhone: '500100200', contactEmail: 'a.nowak@test.pl',
      street: 'Złota', buildingNr: '44', zip: '00-001', city: 'Warszawa',
      lastActionDate: new Date(Date.now() - 5 * 86400000).toISOString(),
      employeesTotal: 100, employeesUop: 80, employeesUz: 20, avgWageUop: 5000, avgWageUz: 3000, serviceFeePercent: 10,
      offerSentDate: '2023-08-15T10:00:00Z',
      contractSignedDate: '2023-08-20T10:00:00Z',
      savedOffers: [],
      activityHistory: [
        { id: 'a1', type: 'MEETING', description: 'Spotkanie wstępne', date: new Date(Date.now() - 10 * 86400000).toISOString(), authorId: 'u4' },
        { id: 'a2', type: 'CALL', description: 'Follow up', date: new Date(Date.now() - 5 * 86400000).toISOString(), authorId: 'u4' },
      ],
    },
    {
      id: 'c2', name: 'Budex', nip: '9876543210', status: 'IN_TALKS',
      ownerId: 'u10', opiekunDisplay: 'Jakub Jabłoński (GDJJ)', contactName: 'Jan Kowalski', contactPhone: '600300400', contactEmail: 'jan@budex.pl',
      street: 'Długa', buildingNr: '5', zip: '80-001', city: 'Gdańsk',
      lastActionDate: new Date().toISOString(),
      employeesTotal: 50, employeesUop: 40, employeesUz: 10, avgWageUop: 4500, avgWageUz: 3500, serviceFeePercent: 10,
      offerSentDate: new Date().toISOString(),
      reservationEndDate: new Date(Date.now() + 75 * 86400000).toISOString(),
      savedOffers: [],
      activityHistory: [],
    },
    {
      id: 'c3', name: 'Nowoczesne Rozwiązania IT S.A.', nip: '7771234567', status: 'SIGNED',
      ownerId: 'u10', opiekunDisplay: 'Jakub Jabłoński (GDJJ)', contactName: 'Maria Technologiczna', contactPhone: '777100200', contactEmail: 'm.techno@nrit.pl',
      street: 'Głogowska', buildingNr: '18', zip: '60-734', city: 'Poznań',
      lastActionDate: new Date('2023-05-12T10:00:00Z').toISOString(),
      employeesTotal: 150, employeesUop: 120, employeesUz: 30, avgWageUop: 8000, avgWageUz: 4000, serviceFeePercent: 12,
      offerSentDate: '2023-05-01T10:00:00Z',
      contractSignedDate: '2023-05-10T10:00:00Z',
      savedOffers: [],
      activityHistory: [],
    },
    {
      id: 'c4', name: 'Global Trans Logistyka Sp. z o.o.', nip: '8881234567', status: 'SIGNED',
      ownerId: 'u10', opiekunDisplay: 'Jakub Jabłoński (GDJJ)', contactName: 'Krzysztof Kierowca', contactPhone: '888200300', contactEmail: 'k.kierowca@globaltrans.pl',
      street: 'Robotnicza', buildingNr: '70', zip: '53-608', city: 'Wrocław',
      lastActionDate: new Date('2023-04-22T10:00:00Z').toISOString(),
      employeesTotal: 300, employeesUop: 250, employeesUz: 50, avgWageUop: 5500, avgWageUz: 4500, serviceFeePercent: 9,
      offerSentDate: '2023-04-10T10:00:00Z',
      contractSignedDate: '2023-04-20T10:00:00Z',
      savedOffers: [],
      activityHistory: [],
    },
    {
      id: 'c5', name: 'Build-Max Budownictwo', nip: '9991234567', status: 'SIGNED',
      ownerId: 'u10', opiekunDisplay: 'Jakub Jabłoński (GDJJ)', contactName: 'Paweł Murarz', contactPhone: '999300400', contactEmail: 'p.murarz@build-max.pl',
      street: 'Wadowicka', buildingNr: '8', zip: '30-415', city: 'Kraków',
      lastActionDate: new Date('2023-06-02T10:00:00Z').toISOString(),
      employeesTotal: 95, employeesUop: 80, employeesUz: 15, avgWageUop: 6000, avgWageUz: 4200, serviceFeePercent: 11,
      offerSentDate: '2023-05-20T10:00:00Z',
      contractSignedDate: '2023-06-01T10:00:00Z',
      savedOffers: [],
      activityHistory: [],
    },
    {
      id: 'c6', name: 'Fresco Market', nip: '1112223344', status: 'SIGNED',
      ownerId: 'u10', opiekunDisplay: 'Jakub Jabłoński (GDJJ)', contactName: 'Joanna Sprzedawczyni', contactPhone: '111400500', contactEmail: 'j.sprzedawczyni@fresco.pl',
      street: 'Krzywoustego', buildingNr: '10', zip: '70-250', city: 'Szczecin',
      lastActionDate: new Date('2023-03-18T10:00:00Z').toISOString(),
      employeesTotal: 50, employeesUop: 45, employeesUz: 5, avgWageUop: 4200, avgWageUz: 3100, serviceFeePercent: 10,
      offerSentDate: '2023-03-01T10:00:00Z',
      contractSignedDate: '2023-03-15T10:00:00Z',
      savedOffers: [],
      activityHistory: [],
    },
    {
      id: 'c7', name: 'Creative Minds Agencja Marketingowa', nip: '5556667788', status: 'SIGNED',
      ownerId: 'u10', opiekunDisplay: 'Jakub Jabłoński (GDJJ)', contactName: 'Tomasz Kreatywny', contactPhone: '555600700', contactEmail: 't.kreatywny@creative.pl',
      street: 'Wały Piastowskie', buildingNr: '1', zip: '80-855', city: 'Gdańsk',
      lastActionDate: new Date('2023-02-28T10:00:00Z').toISOString(),
      employeesTotal: 35, employeesUop: 25, employeesUz: 10, avgWageUop: 7500, avgWageUz: 5000, serviceFeePercent: 15,
      offerSentDate: '2023-02-10T10:00:00Z',
      contractSignedDate: '2023-02-25T10:00:00Z',
      savedOffers: [],
      activityHistory: [],
    },
  ]))

  const employees = ref<Employee[]>(load('employees', [
    { id: 'e1', clientId: 'c1', name: 'Jan Pracownik', contractType: 'UoP', benefitAmount: 500 },
    { id: 'e2', clientId: 'c1', name: 'Anna Zlecenie', contractType: 'UZ', benefitAmount: 300 },
  ]))

  const invoices = ref<Invoice[]>(load('invoices', [
    { id: 'inv1', number: 'FV/09/2023', clientId: 'c1', issueDate: '2023-09-01T10:00:00Z', amountNet: 10000, amountGross: 12300, serviceFeeNet: 1000, status: 'PAID', pdfUrl: '#' },
  ]))

  const commissionConfig = ref<CommissionConfig>(load('commissionConfig', {
    salesCommissionFirstMonthLt14: 0.90,
    salesCommissionFirstMonthGt14: 0.80,
    salesCommissionRenewal: 0.04,
  }))

  const notifications = ref<Notification[]>(load('notifications', [
    { id: 'n1', userId: 'u4', type: 'CRITICAL', message: 'Wymagane działanie: Test Corp (brak akcji od 3 dni)', date: new Date().toISOString(), read: false },
  ]))

  const auditLogs = ref<AuditLog[]>(load('auditLogs', []))

  const emails = ref<Email[]>(load('emails', [
    { id: 'email1', fromName: 'System Stratton', fromEmail: 'system@stratton-prime.pl', toEmail: 'j.jablonski@stratton-prime.pl', subject: 'Witamy w nowym CRM!', body: '<h1>Witaj Jakub,</h1><p>Twój dostęp do nowego systemu CRM został aktywowany. Zaloguj się, aby rozpocząć zarządzanie swoim zespołem.</p>', date: new Date(Date.now() - 86400000).toISOString(), read: false, folder: 'INBOX' },
    { id: 'email2', fromName: 'Super Admin', fromEmail: 'admin@stratton.com', toEmail: 'j.jablonski@stratton-prime.pl', subject: 'Kwartalne cele sprzedażowe', body: '<p>Cześć Jakub,</p><p>Proszę o przygotowanie propozycji celów sprzedażowych na Q3 dla Twojego regionu. Omówimy je na naszym spotkaniu w piątek.</p><p>Pozdrawiam,<br>Admin</p>', date: new Date().toISOString(), read: true, folder: 'INBOX' },
    { id: 'email3', fromName: 'Jakub Jabłoński', fromEmail: 'j.jablonski@stratton-prime.pl', toEmail: 'j.adamczyk@stratton-prime.pl', subject: 'Re: Prośba o raport', body: '<p>Cześć Jarek, raport wygląda dobrze, dzięki za szybkie przygotowanie.</p>', date: new Date(Date.now() - 2 * 86400000).toISOString(), read: true, folder: 'SENT' },
  ]))

  const knowledgeFiles = ref<KnowledgeFile[]>(load('knowledgeFiles', [
    { id: 'kf1', name: 'Wzór umowy ramowej v3.1.docx', description: 'Aktualny wzór umowy o współpracy z klientem.', category: 'UMOWY', fileType: 'docx', fileUrl: '#', addedDate: '2023-10-01T10:00:00Z', size: '128 KB' },
    { id: 'kf2', name: 'Poradnik RODO w sprzedaży.pdf', description: 'Kluczowe zagadnienia prawne dotyczące ochrony danych osobowych.', category: 'PRAWO', fileType: 'pdf', fileUrl: '#', addedDate: '2023-09-15T10:00:00Z', size: '1.2 MB' },
    { id: 'kf3', name: 'Proces onboardingu nowego klienta.pdf', description: 'Krok po kroku: od podpisania umowy do pierwszego rozliczenia.', category: 'PROCESY', fileType: 'pdf', fileUrl: '#', addedDate: '2023-10-05T10:00:00Z', size: '450 KB' },
    { id: 'kf4', name: 'Prezentacja handlowa - Stratton Prime.pptx', description: 'Oficjalna prezentacja dla potencjalnych klientów.', category: 'MARKETING', fileType: 'pptx', fileUrl: '#', addedDate: '2023-09-20T10:00:00Z', size: '5.8 MB' },
    { id: 'kf5', name: 'Wzór NDA.docx', description: 'Umowa o zachowaniu poufności.', category: 'UMOWY', fileType: 'docx', fileUrl: '#', addedDate: '2023-08-01T10:00:00Z', size: '88 KB' },
  ]))

  const autentiDocuments = ref<AutentiDocument[]>(load('autentiDocuments', []))

  watch(structurePassword, (value) => persist('structurePassword', value))
  watch(users, (value) => persist('users', value), { deep: true })
  watch(clients, (value) => persist('clients', value), { deep: true })
  watch(employees, (value) => persist('employees', value), { deep: true })
  watch(invoices, (value) => persist('invoices', value), { deep: true })
  watch(commissionConfig, (value) => persist('commissionConfig', value), { deep: true })
  watch(notifications, (value) => persist('notifications', value), { deep: true })
  watch(auditLogs, (value) => persist('auditLogs', value), { deep: true })
  watch(emails, (value) => persist('emails', value), { deep: true })
  watch(knowledgeFiles, (value) => persist('knowledgeFiles', value), { deep: true })
  watch(autentiDocuments, (value) => persist('autentiDocuments', value), { deep: true })

  const rawAddClient = (client: Client) => {
    clients.value = [client, ...clients.value]
  }

  const rawUpdateClient = (id: string, partial: Partial<Client>) => {
    clients.value = clients.value.map((client) => (client.id === id ? { ...client, ...partial } : client))
  }

  const rawAddNotification = (notification: Notification) => {
    notifications.value = [notification, ...notifications.value]
  }

  const rawUpdateNotifications = (updater: (items: Notification[]) => Notification[]) => {
    notifications.value = updater(notifications.value)
  }

  const rawAddUser = (user: User) => {
    users.value = [...users.value, user]
  }

  const rawUpdateUser = (id: string, partial: Partial<User>) => {
    users.value = users.value.map((user) => (user.id === id ? { ...user, ...partial } : user))
  }

  const rawUpdateUsers = (updater: (items: User[]) => User[]) => {
    users.value = updater(users.value)
  }

  const rawAddEmployee = (employee: Employee) => {
    employees.value = [...employees.value, employee]
  }

  const rawAddInvoice = (invoice: Invoice) => {
    invoices.value = [invoice, ...invoices.value]
  }

  const rawUpdateInvoice = (id: string, partial: Partial<Invoice>) => {
    invoices.value = invoices.value.map((invoice) => (invoice.id === id ? { ...invoice, ...partial } : invoice))
  }

  const rawAddAutentiDocument = (doc: AutentiDocument) => {
    autentiDocuments.value = [doc, ...autentiDocuments.value]
  }

  const rawUpdateAutentiDocument = (id: string, partial: Partial<AutentiDocument>) => {
    autentiDocuments.value = autentiDocuments.value.map((doc) => (doc.id === id ? { ...doc, ...partial } : doc))
  }

  const rawUpdateEmails = (updater: (items: Email[]) => Email[]) => {
    emails.value = updater(emails.value)
  }

  const verifyStructurePassword = (input: string) => input === structurePassword.value

  const updateStructurePassword = (newPass: string) => {
    structurePassword.value = newPass
    logAction('admin', 'UPDATE_PASSWORD', 'Zmieniono haslo administracyjne struktury')
  }

  const getSubtreeUserIds = (rootUserId: string): string[] => {
    const directReports = users.value.filter((user) => user.parentId === rootUserId && !user.isRemovedFromStructure)
    let ids = directReports.map((user) => user.id)
    directReports.forEach((child) => {
      ids = [...ids, ...getSubtreeUserIds(child.id)]
    })
    return ids
  }

  const globalSearch = (query: string, userRole: UserRole, userId: string) => {
    if (!query || query.length < 2) return []
    const lowerQ = query.toLowerCase()

    let visibleClients = clients.value
    if (userRole === 'SALES') visibleClients = visibleClients.filter((client) => client.ownerId === userId)

    const clientResults = visibleClients
      .filter((client) => client.name.toLowerCase().includes(lowerQ) || client.nip.includes(query))
      .map((client) => ({ type: 'Klient', id: client.id, label: client.name, subLabel: `NIP: ${client.nip}`, route: '/app/clients' }))

    const userResults = users.value
      .filter((user) => !user.isRemovedFromStructure && user.name.toLowerCase().includes(lowerQ))
      .map((user) => ({ type: 'Pracownik', id: user.id, label: user.name, subLabel: user.role, route: '/app/structure' }))

    return [...clientResults, ...userResults]
  }

  const logAction = (actorId: string, action: string, details: string, targetId?: string) => {
    if (auth.enabled) {
      api.post('/v1/crm-audit-logs', {
        actor_id: actorId,
        action,
        details,
        target_id: targetId || null,
      }).catch(() => undefined)
      return
    }
    const log: AuditLog = {
      id: Math.random().toString(36).substr(2, 9),
      actorId,
      action,
      details,
      targetId,
      date: new Date().toISOString(),
    }
    auditLogs.value = [log, ...auditLogs.value]
  }

  const updateCommissionConfig = (config: CommissionConfig) => {
    commissionConfig.value = config
    logAction('admin', 'UPDATE_CONFIG', 'Zaktualizowano progi prowizyjne')
  }

  return {
    structurePassword,
    users,
    clients,
    employees,
    invoices,
    notifications,
    commissionConfig,
    auditLogs,
    emails,
    knowledgeFiles,
    autentiDocuments,
    rawAddClient,
    rawUpdateClient,
    rawAddNotification,
    rawUpdateNotifications,
    rawAddUser,
    rawUpdateUser,
    rawUpdateUsers,
    rawAddEmployee,
    rawAddInvoice,
    rawUpdateInvoice,
    rawAddAutentiDocument,
    rawUpdateAutentiDocument,
    rawUpdateEmails,
    verifyStructurePassword,
    updateStructurePassword,
    getSubtreeUserIds,
    globalSearch,
    logAction,
    updateCommissionConfig,
  }
})

export type { User, UserRole, Rank, Client, SavedOffer, ClientActivity, Employee, Invoice, CommissionConfig, Notification, AuditLog, Email, KnowledgeFile, FileCategory, AutentiDocument }
