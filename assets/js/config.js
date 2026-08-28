/* ============================================================
   CONFIGURACION: MONEDAS (tasas referenciales por 1 USD)
   NOTA: los PRODUCTOS ya NO estan codificados aqui. Se cargan
   desde la base de datos via la API REST (ver products.js).
   ============================================================ */
var CURRENCIES = {
  USD:{name:'Dolar (USD)',symbol:'$',rate:1,dec:2},
  EUR:{name:'Euro (EUR)',symbol:'€',rate:0.92,dec:2},
  GBP:{name:'Libra (GBP)',symbol:'£',rate:0.79,dec:2},
  MXN:{name:'Peso Mexicano',symbol:'$',rate:17.2,dec:2},
  COP:{name:'Peso Colombiano',symbol:'$',rate:4100,dec:0},
  ARS:{name:'Peso Argentino',symbol:'$',rate:990,dec:0},
  CLP:{name:'Peso Chileno',symbol:'$',rate:930,dec:0},
  PEN:{name:'Sol Peruano',symbol:'S/',rate:3.75,dec:2},
  BOB:{name:'Boliviano',symbol:'Bs',rate:6.9,dec:2},
  UYU:{name:'Peso Uruguayo',symbol:'$U',rate:39,dec:0},
  PYG:{name:'Guarani',symbol:'₲',rate:7300,dec:0},
  GTQ:{name:'Quetzal',symbol:'Q',rate:7.7,dec:2},
  CRC:{name:'Colon (CRC)',symbol:'₡',rate:510,dec:0},
  DOP:{name:'Peso Dominicano',symbol:'RD$',rate:59,dec:2},
  VES:{name:'Bolivar (VES)',symbol:'Bs',rate:36,dec:2},
  BRL:{name:'Real Brasileno',symbol:'R$',rate:5.4,dec:2},
  CAD:{name:'Dolar Canadiense',symbol:'C$',rate:1.36,dec:2}
};
