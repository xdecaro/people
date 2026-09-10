(() => {
  'use strict';

  const tinLabels = {
    ALB: 'Numri personal',
    AND: 'NRT',
    AUT: 'Steuernummer',
    BEL: 'Numéro national / Rijksregisternummer',
    BGR: 'EGN',
    BIH: 'JMBG',
    BLR: 'Identification number',
    CHE: 'AHV / AVS',
    CYP: 'TIC',
    CZE: 'Rodné číslo',
    DEU: 'Steuerliche Identifikationsnummer',
    DNK: 'CPR-nummer',
    ESP: 'NIF',
    EST: 'Isikukood',
    FIN: 'Henkilötunnus',
    FRA: 'Numéro fiscal',
    GBR: 'UTR / NINO',
    GRC: 'AFM',
    HRV: 'OIB',
    HUN: 'Adóazonosító jel',
    IRL: 'PPS Number',
    ISL: 'Kennitala',
    ITA: 'Codice fiscale',
    LIE: 'Steuernummer',
    LTU: 'Asmens kodas',
    LUX: 'Matricule',
    LVA: 'Personas kods',
    MCO: 'TIN',
    MDA: 'IDNP',
    MKD: 'EMBG',
    MLT: 'Tax number',
    MNE: 'JMBG',
    NLD: 'BSN',
    NOR: 'Fødselsnummer',
    POL: 'PESEL / NIP',
    PRT: 'NIF',
    ROU: 'CNP',
    RUS: 'INN',
    SMR: 'Codice ISS',
    SRB: 'JMBG',
    SVK: 'Rodné číslo',
    SVN: 'Davčna številka',
    SWE: 'Personnummer',
    TUR: 'T.C. Kimlik No / VKN',
    UKR: 'РНОКПП',
    VAT: 'Codice fiscale'
  };

  const updateTinLabel = () => {
    const nationality = document.getElementById('jform_nationality_code');
    const label = document.querySelector('label[for="jform_tax_identifier"]');

    if (!nationality || !label) {
      return;
    }

    const localName = tinLabels[nationality.value] || '';
    label.textContent = localName && localName !== 'TIN' ? `TIN - ${localName}` : 'TIN';
  };

  document.addEventListener('DOMContentLoaded', () => {
    const nationality = document.getElementById('jform_nationality_code');

    if (!nationality) {
      return;
    }

    nationality.addEventListener('change', updateTinLabel);
    updateTinLabel();
  });
})();
