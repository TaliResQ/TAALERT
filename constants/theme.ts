import { StyleSheet } from 'react-native';

/* For Bottom Navbar */
export const BottomNavStyles = StyleSheet.create({
  navBarContainer: {
    position: 'absolute',
    bottom: 20,
    left: 20,
    right: 20,
  },
  navBar: {
    flexDirection: 'row',
    backgroundColor: 'white',
    height: 70,
    borderRadius: 35,
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 15,
    elevation: 15,
    shadowColor: '#000',
    shadowOpacity: 0.1,
    shadowRadius: 10,
  },
  navItem: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  activeDot: {
    width: 5,
    height: 5,
    borderRadius: 2.5,
    backgroundColor: '#0096FF',
    position: 'absolute',
    bottom: -12,
  },
  plusBtnContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    position: 'relative',
  },
  glowRing: {
    position: 'absolute',
    top: -35,
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: 'rgba(37, 165, 254, 0.2)',
    elevation: 0,
  },
  plusBtn: {
    width: 60,
    height: 60,
    backgroundColor: '#25A5FE',
    borderRadius: 30,
    top: -25,
    justifyContent: 'center',
    alignItems: 'center',
    elevation: 8,
    shadowColor: '#25A5FE',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.4,
    shadowRadius: 10,
  },
});





/* For Index */
export const COLORS = {
  background: '#F0F5F9',
  textDark: '#1E293B',
  textMuted: '#64748B',
  primaryBlue: '#25A5FE',
  cardDark: '#1E3A5F',
  danger: '#EF4444',
  white: '#FFFFFF',
  badgeUpdateBg: '#E8F1FF',
  badgeUpdateText: '#2563EB',
  badgeReportBg: '#FFF4E5',
  badgeReportText: '#000000',
  badgeSafeBg: '#E6F4EA',
  badgeSafeText: '#16A34A',
};

export const IndexStyles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  scrollContent: {
    padding: 24,
    paddingTop: 10,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 24,
  },
  greetingText: {
    fontSize: 16,
    color: COLORS.textMuted,
    marginBottom: 2,
  },
  nameText: {
    fontSize: 24,
    fontWeight: '700',
    color: COLORS.textDark,
  },
  notificationBtn: {
    backgroundColor: COLORS.white,
    padding: 12,
    borderRadius: 50,
    position: 'relative',
  },
  notificationDot: {
    position: 'absolute',
    top: 12,
    right: 12,
    width: 8,
    height: 8,
    backgroundColor: COLORS.danger,
    borderRadius: 4,
    borderWidth: 1.5,
    borderColor: COLORS.white,
  },
  statusCard: {
    backgroundColor: COLORS.primaryBlue,
    borderRadius: 24,
    padding: 24,
    marginBottom: 32,
  },
  statusHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  statusHeaderText: {
    color: COLORS.white,
    fontSize: 12,
    fontWeight: '600',
    marginLeft: 6,
    letterSpacing: 0.5,
  },
  volcanoName: {
    color: COLORS.white,
    fontSize: 28,
    fontWeight: '700',
    marginBottom: 4,
  },
  alertLevel: {
    color: COLORS.white,
    fontSize: 15,
    opacity: 0.9,
    marginBottom: 16,
  },
  statusBadgesRow: {
    flexDirection: 'row',
    gap: 10,
  },
  statusBadge: {
    backgroundColor: 'rgba(255, 255, 255, 0.25)',
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
  },
  statusBadgeText: {
    color: COLORS.white,
    fontSize: 12,
    fontWeight: '500',
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: COLORS.textMuted,
    letterSpacing: 0.5,
    marginBottom: 16,
  },
  quickActionsContainer: {
    flexDirection: 'row',
    marginBottom: 32,
    gap: 16,
  },
  sosCard: {
    flex: 1,
    backgroundColor: COLORS.cardDark,
    borderRadius: 24,
    padding: 24,
    justifyContent: 'space-between',
    minHeight: 200,
  },
  sosIconContainer: {
    width: 56,
    height: 56,
    backgroundColor: COLORS.danger,
    borderRadius: 28,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 20,
  },
  sosTitle: {
    color: COLORS.white,
    fontSize: 22,
    fontWeight: '700',
    marginBottom: 8,
    lineHeight: 28,
  },
  sosSub: {
    color: COLORS.white,
    fontSize: 13,
    opacity: 0.7,
  },
  rightActionsColumn: {
    flex: 1,
    gap: 16,
  },
  actionCard: {
    backgroundColor: COLORS.white,
    borderRadius: 24,
    padding: 20,
    flex: 1,
    justifyContent: 'center',
  },
  actionIconContainer: {
    width: 40,
    height: 40,
    backgroundColor: '#E8F1FF',
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  actionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: COLORS.textDark,
    marginBottom: 4,
  },
  actionSub: {
    fontSize: 13,
    color: COLORS.textMuted,
  },
  activityHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  sectionTitleDark: {
    fontSize: 18,
    fontWeight: '700',
    color: COLORS.textDark,
  },
  seeAllText: {
    fontSize: 14,
    color: COLORS.primaryBlue,
    fontWeight: '600',
  },
  activityCard: {
    backgroundColor: COLORS.white,
    borderRadius: 20,
    padding: 20,
    marginBottom: 12,
  },
  activityCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  activityBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  activityBadgeText: {
    fontSize: 10,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  timeContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  timeText: {
    fontSize: 13,
    color: COLORS.textMuted,
  },
  activityTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: COLORS.textDark,
    marginBottom: 6,
  },
  activityDesc: {
    fontSize: 14,
    color: COLORS.textMuted,
    lineHeight: 20,
  },
  floatingNavContainer: {
    position: 'absolute',
    bottom: 20,
    left: 20,
    right: 20,
    alignItems: 'center',
  },
  floatingNav: {
    flexDirection: 'row',
    backgroundColor: COLORS.white,
    height: 70,
    borderRadius: 35,
    width: '100%',
    paddingHorizontal: 10,
    alignItems: 'center',
    justifyContent: 'space-between',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.05,
    shadowRadius: 10,
    elevation: 5,
  },
  navItem: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    height: '100%',
  },
  activeNavDot: {
    width: 4,
    height: 4,
    backgroundColor: COLORS.primaryBlue,
    borderRadius: 2,
    position: 'absolute',
    bottom: 12,
  },
  centerNavWrapper: {
    flex: 1.2,
    alignItems: 'center',
    justifyContent: 'center',
    position: 'relative',
  },
  centerNavRing: {
    position: 'absolute',
    top: -25,
    width: 76,
    height: 76,
    backgroundColor: '#D6EEFF',
    borderRadius: 38,
    alignItems: 'center',
    justifyContent: 'center',
  },
  centerNavBtn: {
    width: 60,
    height: 60,
    backgroundColor: COLORS.primaryBlue,
    borderRadius: 30,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: COLORS.primaryBlue,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 8,
  },
  // --- New SOS Modal Styles ---
  sosModalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.85)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  sosModalContent: {
    backgroundColor: COLORS.cardDark,
    borderRadius: 32,
    padding: 32,
    width: '100%',
    alignItems: 'center',
    elevation: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.3,
    shadowRadius: 20,
  },
  sosModalTitle: {
    color: COLORS.white,
    fontSize: 26,
    fontWeight: '800',
    marginBottom: 8,
    textAlign: 'center',
    letterSpacing: 1,
  },
  sosModalDesc: {
    color: COLORS.white,
    fontSize: 16,
    opacity: 0.8,
    textAlign: 'center',
    marginBottom: 32,
    lineHeight: 24,
  },
  holdInstruction: {
    color: COLORS.white,
    fontSize: 14,
    opacity: 0.6,
    marginBottom: 16,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  holdButton: {
    width: 140,
    height: 140,
    borderRadius: 70,
    backgroundColor: COLORS.danger,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 32,
    elevation: 8,
    shadowColor: COLORS.danger,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.6,
    shadowRadius: 15,
    borderWidth: 4,
    borderColor: 'rgba(255, 255, 255, 0.2)',
  },
  holdButtonText: {
    color: COLORS.white,
    fontSize: 48,
    fontWeight: '800',
  },
  cancelSosBtn: {
    paddingVertical: 12,
    paddingHorizontal: 32,
    borderRadius: 24,
    backgroundColor: 'rgba(255,255,255,0.1)',
  },
  cancelSosText: {
    color: COLORS.white,
    fontSize: 16,
    fontWeight: '600',
  },
});



/* For Profile Screen */
export const ProfileStyles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F0F9FF' },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 25,
    paddingVertical: 20,
  },
  headerTitle: { fontSize: 28, fontWeight: '800', color: '#0F172A' },
  settingsBtn: {
    width: 44,
    height: 44,
    backgroundColor: '#E2E8F0',
    borderRadius: 22,
    justifyContent: 'center',
    alignItems: 'center',
  },
  scrollContent: { paddingHorizontal: 20 },
  
  // Profile Card
  profileCard: {
    backgroundColor: 'white',
    borderRadius: 30,
    padding: 20,
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 20,
    elevation: 2,
    shadowColor: '#000', shadowOpacity: 0.05, shadowRadius: 10,
  },
  avatar: {
    width: 70,
    height: 70,
    borderRadius: 35,
    backgroundColor: '#25A5FE',
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: { color: 'white', fontSize: 28, fontWeight: 'bold' },
  profileInfo: { marginLeft: 15, flex: 1 },
  userName: { fontSize: 20, fontWeight: 'bold', color: '#1E293B' },
  locationRow: { flexDirection: 'row', alignItems: 'center', marginTop: 4 },
  locationText: { fontSize: 13, color: '#64748B', marginLeft: 4 },
  badge: {
    backgroundColor: '#DCFCE7',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 10,
    alignSelf: 'flex-start',
    marginTop: 8,
  },
  badgeText: { color: '#16A34A', fontSize: 10, fontWeight: 'bold' },

  // Stats
  statsRow: { flexDirection: 'row', gap: 12, marginBottom: 25 },
  statCard: {
    flex: 1,
    backgroundColor: 'white',
    borderRadius: 24,
    paddingVertical: 20,
    alignItems: 'center',
    elevation: 2,
    shadowColor: '#000', shadowOpacity: 0.05, shadowRadius: 10,
  },
  statValue: { fontSize: 24, fontWeight: '800', color: '#1E293B' },
  statLabel: { fontSize: 10, fontWeight: 'bold', color: '#94A3B8', marginTop: 4 },

  // Menu
  menuContainer: {
    backgroundColor: 'white',
    borderRadius: 30,
    overflow: 'hidden',
    elevation: 2,
    shadowColor: '#000', shadowOpacity: 0.05, shadowRadius: 10,
  },
  menuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#F1F5F9',
  },
  menuIconBox: {
    width: 40,
    height: 40,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  menuTextContainer: { flex: 1, marginLeft: 15 },
  menuLabel: { fontSize: 15, fontWeight: '600', color: '#1E293B' },
  menuSubText: { fontSize: 12, color: '#64748B', marginTop: 2 },
});





/* For Maps */
export const MapStyles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F0F9FF' },

  // Map Decoration
  mapContainer: { flex: 1, overflow: 'hidden' },
  road: { position: 'absolute', backgroundColor: '#FFFFFF', opacity: 0.8 },
  roadCurve: { position: 'absolute', borderTopWidth: 10, borderColor: '#FFFFFF', opacity: 0.6 },
  dangerZoneCircle: {
    position: 'absolute',
    top: '40%',
    left: '25%',
    width: 250,
    height: 250,
    borderRadius: 125,
    backgroundColor: 'rgba(0, 150, 255, 0.15)',
  },
  craterDashedRing: {
    position: 'absolute',
    top: '48.5%',
    left: '48.5%',
    width: 70,
    height: 70,
    borderRadius: 35,
    borderWidth: 1.5,
    borderColor: '#EF4444',
    borderStyle: 'dashed',
  },

  // Pins
  pinWrapper: { position: 'absolute', alignItems: 'center' },
  pinDot: { borderWidth: 2, borderColor: 'white', elevation: 3 },
  pinLabel: { fontSize: 10, fontWeight: '700', color: '#1E293B', marginTop: 2, backgroundColor: 'rgba(255,255,255,0.7)', paddingHorizontal: 4, borderRadius: 4 },

  // Top Search
  topOverlay: { position: 'absolute', top: 50, left: 20, right: 20 },
  searchBar: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'white',
    paddingHorizontal: 15,
    paddingVertical: 12,
    borderRadius: 16,
    elevation: 5,
    shadowColor: '#000', shadowOpacity: 0.05, shadowRadius: 10,
  },
  searchInput: { flex: 1, marginLeft: 10, fontSize: 14, color: '#1E293B' },
  layersBtn: { backgroundColor: '#0096FF', padding: 8, borderRadius: 12 },

  // Bottom Info Card
  bottomCardContainer: { position: 'absolute', bottom: 110, left: 15, right: 15 },
  bottomCard: {
    backgroundColor: 'white',
    borderRadius: 30,
    padding: 20,
    paddingTop: 10,
    elevation: 10,
    shadowColor: '#000', shadowOpacity: 0.1, shadowRadius: 15,
  },
  dragHandle: { width: 40, height: 4, backgroundColor: '#E2E8F0', borderRadius: 2, alignSelf: 'center', marginBottom: 15 },
  infoRow: { flexDirection: 'row', gap: 12 },
  dangerIconBg: { width: 44, height: 44, borderRadius: 12, backgroundColor: '#FEE2E2', justifyContent: 'center', alignItems: 'center' },
  dangerIconDot: { width: 10, height: 10, borderRadius: 5, backgroundColor: '#EF4444' },
  textColumn: { flex: 1 },
  dangerTag: { color: '#EF4444', fontSize: 11, fontWeight: 'bold' },
  locationTitle: { fontSize: 18, fontWeight: 'bold', color: '#1E293B' },
  locationSub: { fontSize: 12, color: '#64748B', marginTop: 2 },
  routeButton: { backgroundColor: '#25A5FE', borderRadius: 16, paddingVertical: 16, alignItems: 'center', marginTop: 15 },
  routeButtonText: { color: 'white', fontWeight: 'bold', fontSize: 16 },

  // Bottom Navbar
  navBarContainer: { position: 'absolute', bottom: 20, left: 20, right: 20 },
  navBar: {
    flexDirection: 'row',
    backgroundColor: 'white',
    height: 70,
    borderRadius: 35,
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 15,
    elevation: 10,
    shadowColor: '#000', shadowOpacity: 0.1, shadowRadius: 10,
  },
  navItem: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  activeDot: { width: 4, height: 4, borderRadius: 2, backgroundColor: '#0096FF', position: 'absolute', bottom: -10 },
  plusBtnContainer: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  plusBtn: {
    width: 60,
    height: 60,
    backgroundColor: '#0096FF',
    borderRadius: 30,
    top: -25,
    justifyContent: 'center',
    alignItems: 'center',
    elevation: 5,
    shadowColor: '#0096FF', shadowOpacity: 0.4, shadowRadius: 8,
  }
});





/* For Report */
export const ReportStyles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F8FAFC' },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 20,
        paddingVertical: 15,
    },
    backBtn: {
        width: 40,
        height: 40,
        borderRadius: 20,
        backgroundColor: '#F1F5F9',
        justifyContent: 'center',
        alignItems: 'center',
    },
    headerTitle: {
        fontSize: 20,
        fontWeight: '700',
        color: '#1E293B',
        marginLeft: 15,
    },
    scrollContent: { padding: 20 },
    label: {
        fontSize: 12,
        fontWeight: 'bold',
        color: '#94A3B8',
        marginBottom: 12,
        marginTop: 20,
        letterSpacing: 1,
    },
    // Type Chips
    typeGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
    typeChip: {
        paddingHorizontal: 20,
        paddingVertical: 10,
        borderRadius: 25,
        backgroundColor: 'white',
        borderWidth: 1,
        borderColor: '#E2E8F0',
    },
    typeChipActive: {
        backgroundColor: '#25A5FE',
        borderColor: '#25A5FE',
    },
    typeChipText: { color: '#1E293B', fontWeight: '500' },
    typeChipTextActive: { color: 'white' },

    // Inputs
    inputContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#F1F5F9',
        borderRadius: 15,
        paddingHorizontal: 15,
        height: 55,
    },
    inputIcon: { marginRight: 10 },
    input: { flex: 1, fontSize: 15, color: '#1E293B' },

    textAreaContainer: {
        backgroundColor: '#F1F5F9',
        borderRadius: 15,
        padding: 15,
        minHeight: 120,
    },
    textArea: { fontSize: 15, color: '#1E293B' },

    // Photo Box
    photoBox: {
        height: 180,
        borderRadius: 20,
        borderWidth: 1.5,
        borderColor: '#25A5FE',
        borderStyle: 'dashed',
        justifyContent: 'center',
        alignItems: 'center',
        backgroundColor: '#F0F9FF',
    },
    photoText: {
        marginTop: 10,
        color: '#25A5FE',
        fontWeight: '600',
        fontSize: 14,
    },

    // Footer
    footer: {
        position: 'absolute',
        bottom: 110, 
        left: 0,
        right: 0,
        paddingHorizontal: 20, 
    },
    submitBtn: {
        backgroundColor: '#25A5FE',
        height: 60,
        borderRadius: 20, 
        justifyContent: 'center',
        alignItems: 'center',
        elevation: 4,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 4,
    },
    submitBtnText: {
        color: 'white',
        fontSize: 16,
        fontWeight: 'bold',
    },
});





/* For Resources Screen */
export const ResourceStyles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F0F9FF' },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
    paddingHorizontal: 25,
    paddingTop: 20,
    paddingBottom: 20,
  },
  headerSub: { fontSize: 16, color: '#64748B', fontWeight: '500' },
  headerTitle: { fontSize: 32, fontWeight: '800', color: '#0F172A' },
  topIconBtn: {
    width: 48,
    height: 48,
    backgroundColor: '#E0F2FE',
    borderRadius: 14,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 5,
  },
  scrollContent: { paddingHorizontal: 20 },
  
  // Featured Card
  featuredCard: {
    backgroundColor: '#113366', // Deep Blue
    borderRadius: 30,
    padding: 25,
    marginBottom: 25,
    elevation: 5,
    shadowColor: '#000', shadowOpacity: 0.2, shadowRadius: 10,
  },
  featuredLabel: { color: 'rgba(255,255,255,0.6)', fontSize: 11, fontWeight: 'bold', letterSpacing: 1 },
  featuredTitle: { color: 'white', fontSize: 22, fontWeight: 'bold', marginTop: 10, lineHeight: 28 },
  featuredSub: { color: 'rgba(255,255,255,0.6)', fontSize: 13, marginTop: 8 },
  readBtn: {
    backgroundColor: 'white',
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 15,
    alignSelf: 'flex-start',
    marginTop: 20,
  },
  readBtnText: { color: '#113366', fontWeight: 'bold', fontSize: 14 },

  // List Items
  listContainer: { gap: 15 },
  card: {
    backgroundColor: 'white',
    borderRadius: 24,
    padding: 16,
    flexDirection: 'row',
    alignItems: 'center',
    elevation: 2,
    shadowColor: '#000', shadowOpacity: 0.05, shadowRadius: 8,
  },
  iconBox: {
    width: 52,
    height: 52,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
  },
  cardTextContainer: { flex: 1, marginLeft: 15 },
  cardTitle: { fontSize: 16, fontWeight: 'bold', color: '#1E293B' },
  cardDesc: { fontSize: 13, color: '#64748B', marginTop: 2 },

recoveryCard: {
  backgroundColor: '#EFF6FF',
  borderWidth: 1,
  borderColor: '#BFDBFE',
  borderRadius: 20,
  padding: 16,
  marginBottom: 22,
},
recoveryCardHeader: {
  flexDirection: 'row',
  alignItems: 'center',
  marginBottom: 14,
},
recoveryIconBox: {
  width: 48,
  height: 48,
  borderRadius: 14,
  backgroundColor: '#DBEAFE',
  alignItems: 'center',
  justifyContent: 'center',
  marginRight: 12,
},
recoveryCardText: {
  flex: 1,
  marginRight: 8,
},
recoveryCardTitle: {
  fontSize: 16,
  fontWeight: '700',
  color: '#1E3A8A',
  marginBottom: 5,
},
recoveryCardDescription: {
  fontSize: 12,
  lineHeight: 18,
  color: '#475569',
},
recoveryTags: {
  flexDirection: 'row',
  flexWrap: 'wrap',
  gap: 7,
},
recoveryTag: {
  backgroundColor: '#DBEAFE',
  borderRadius: 20,
  paddingHorizontal: 10,
  paddingVertical: 6,
},
recoveryTagText: {
  fontSize: 10,
  fontWeight: '600',
  color: '#1D4ED8',
},

});


/* For Eruption Guide Screen */
export const GuideStyles = StyleSheet.create({
  container: { 
    flex: 1, 
    backgroundColor: '#F0F9FF' 
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingTop: 20,
    paddingBottom: 15,
  },
  backBtn: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#E0F2FE',
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 24,
    fontWeight: '800',
    color: '#0F172A',
    marginLeft: 15,
  },
  scrollContent: { 
    paddingHorizontal: 20, 
    paddingBottom: 40,
    paddingTop: 10
  },
  
  // Phase Cards
  card: {
    backgroundColor: 'white',
    borderRadius: 24,
    padding: 24,
    marginBottom: 20,
    elevation: 3,
    shadowColor: '#000', 
    shadowOpacity: 0.05, 
    shadowRadius: 10,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
    borderBottomWidth: 1.5,
    borderBottomColor: '#F1F5F9',
    paddingBottom: 16,
  },
  iconBox: {
    width: 50,
    height: 50,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
  },
  cardTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1E293B',
    marginLeft: 15,
  },
  
  // List items inside cards
  tipRow: {
    flexDirection: 'row',
    marginBottom: 14,
    alignItems: 'flex-start',
  },
  bulletPoint: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: '#25A5FE',
    marginTop: 8,
    marginRight: 12,
  },
  tipText: {
    flex: 1,
    fontSize: 14,
    color: '#475569',
    lineHeight: 22,
  }
});

/* For Welcome / Pre-Login Screen */
export const WelcomeStyles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  scrollContent: {
    padding: 24,
    paddingTop: 40,
  },
  heroSection: {
    alignItems: 'center',
    marginBottom: 40,
  },
  logoContainer: {
    width: 90,
    height: 90,
    backgroundColor: COLORS.primaryBlue,
    borderRadius: 30,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 20,
    elevation: 8,
    shadowColor: COLORS.primaryBlue,
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.3,
    shadowRadius: 12,
  },
  appName: {
    fontSize: 32,
    fontWeight: '900',
    color: COLORS.textDark,
    letterSpacing: 1,
    marginBottom: 8,
  },
  tagline: {
    fontSize: 16,
    color: COLORS.textMuted,
    fontWeight: '500',
  },
  authContainer: {
    backgroundColor: COLORS.white,
    borderRadius: 24,
    padding: 24,
    marginBottom: 32,
    elevation: 3,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 10,
  },
  unlockText: {
    fontSize: 14,
    color: COLORS.textMuted,
    lineHeight: 22,
    textAlign: 'center',
    marginBottom: 24,
  },
  highlightText: {
    color: COLORS.textDark,
    fontWeight: '700',
  },
  buttonRow: {
    flexDirection: 'row',
    gap: 12,
  },
  loginBtn: {
    flex: 1,
    backgroundColor: COLORS.primaryBlue,
    paddingVertical: 16,
    borderRadius: 16,
    alignItems: 'center',
    elevation: 4,
    shadowColor: COLORS.primaryBlue,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.2,
    shadowRadius: 8,
  },
  loginBtnText: {
    color: COLORS.white,
    fontSize: 16,
    fontWeight: '700',
  },
  signUpBtn: {
    flex: 1,
    backgroundColor: '#E8F1FF',
    paddingVertical: 16,
    borderRadius: 16,
    alignItems: 'center',
  },
  signUpBtnText: {
    color: COLORS.primaryBlue,
    fontSize: 16,
    fontWeight: '700',
  },
  publicResourcesHeader: {
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: COLORS.textMuted,
    letterSpacing: 1,
    marginBottom: 4,
  },
  sectionSub: {
    fontSize: 12,
    color: COLORS.textMuted,
  },
  resourcesContainer: {
    gap: 12,
  },
  resourceCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.white,
    padding: 20,
    borderRadius: 20,
    elevation: 2,
    shadowColor: '#000',
    shadowOpacity: 0.03,
    shadowRadius: 8,
  },
  resourceIconBox: {
    width: 48,
    height: 48,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
  },
  resourceTextContent: {
    flex: 1,
    marginLeft: 16,
    marginRight: 8,
  },
  resourceTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: COLORS.textDark,
    marginBottom: 4,
  },
  resourceDesc: {
    fontSize: 13,
    color: COLORS.textMuted,
    lineHeight: 18,
  },
});