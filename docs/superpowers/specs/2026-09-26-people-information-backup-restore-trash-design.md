# People 1.7.28 — Informazioni, backup, restore e cestino

Data: 2026-09-26
Stato: design approvato in chat, in attesa di revisione della specifica prima del piano di implementazione
Target: Joomla 6.1.3, PHP 8.3+

## Obiettivo

Trasformare la vista amministrativa **Informazioni** di `com_xdecaropeople` da semplice riepilogo tecnico a centro operativo e diagnostico di People, mantenendo People proprietario esclusivo dei propri dati.

La stessa release introduce:

- informazioni complete su ambiente, database e componenti collegati;
- backup logico completo dei dati People;
- restore con anteprima, validazione e backup automatico pre-ripristino;
- vero cestino recuperabile per le persone eliminate;
- eliminazione definitiva solo esplicita e protetta;
- log di manutenzione;
- diagnostica di integrità interna;
- nessun accesso diretto alle tabelle private di Organizations, Membership, Competitions, Photos o altri componenti.

## Stato attuale rilevato

People possiede oggi le tabelle:

- `#__xdecaropeople_people`
- `#__xdecaropeople_history`
- `#__xdecaropeople_duplicate_ignores`
- `#__xdecaropeople_merges`

La tabella persone contiene già `state`, UUID univoco, stato persona, metadati di creazione/modifica e i campi anagrafici. Lo storico registra creazioni e modifiche. Esistono già controller separati per Import ed Export.

La vista Informazioni attuale mostra soltanto versione People/Joomla/PHP, Core, stato database, un testo sui componenti collegati e una diagnostica sintetica.

## Decisione sul cestino

Si usa il **cestino Joomla tramite `state = -2` sulla riga originale** invece di copiare la persona in una seconda tabella.

Motivi:

1. ID e UUID rimangono invariati.
2. I riferimenti interni non vengono spezzati.
3. Il ripristino è sicuro e immediato.
4. È coerente con il ciclo di vita standard Joomla.
5. Evita una copia divergente dei dati personali.

Una persona nel cestino non appare negli elenchi ordinari, nei picker o nelle API normali di People. È visibile solo nel filtro Cestino e nel blocco Cancellati della pagina Informazioni.

### Azioni cestino

- **Sposta nel cestino**: `state = -2`, registra attore e data nel log di manutenzione/storico.
- **Ripristina**: riporta la persona allo stato precedente, conservando ID e UUID.
- **Elimina definitivamente**: disponibile solo dal cestino, con `core.delete`, token CSRF e conferma esplicita. Prima della cancellazione si crea una voce audit con UUID, nome visualizzato, ID e data.

La cancellazione definitiva deve mostrare un avviso forte: eventuali riferimenti esterni possono diventare non risolvibili. Se Core EntityReference o capability pubbliche collegate permettono un controllo sicuro dei riferimenti, la UI mostra il risultato; non si interrogano tabelle private di altri componenti.

## Backup: formato e contenuto

Il backup è distinto dall'Export CSV/Excel/PDF. L'export serve al lavoro sui dati; il backup serve al ripristino tecnico.

Formato canonico: **ZIP People Backup** contenente almeno:

- `manifest.json`
- `data.json`
- `SHA256SUMS.txt`

Il manifest include:

- formato backup e versione formato;
- versione People;
- versione schema People;
- versione Joomla;
- data/ora UTC;
- utente che ha creato il backup;
- conteggio righe per tabella;
- elenco esplicito delle tabelle incluse;
- checksum del payload.

Il payload include solo dati posseduti da People e deve conservare i valori tecnici necessari al ripristino, inclusi ID, UUID, stati, metadati, storico, merge e ignore duplicati.

Tabelle incluse nella prima versione:

- `#__xdecaropeople_people`
- `#__xdecaropeople_history`
- `#__xdecaropeople_duplicate_ignores`
- `#__xdecaropeople_merges`
- nuove tabelle People di backup/manutenzione introdotte dalla release, con esclusione del payload binario del backup corrente per evitare backup ricorsivi.

Non vengono incluse tabelle di Core, Organizations, Membership, Competitions, Photos, Documents o Notifications.

## Conservazione backup

Si introduce una tabella metadata `#__xdecaropeople_backups` con almeno:

- `id`
- `uuid`
- `filename`
- `storage_path`
- `sha256`
- `size_bytes`
- `people_count`
- `component_version`
- `schema_version`
- `created`
- `created_by`
- `status`

Il file ZIP viene conservato in uno storage privato People configurabile. Il servizio deve:

1. preferire un percorso privato configurato e scrivibile;
2. rifiutare percorsi non scrivibili;
3. non esporre il percorso fisico in UI;
4. servire il download tramite controller autenticato, non tramite URL diretto al file;
5. usare nomi file non prevedibili basati su UUID;
6. mostrare in Diagnostica se lo storage non è sicuro o non è disponibile.

Non si usa la cache Joomla come storage permanente dei backup.

## Restore

Sono previste tre operazioni distinte.

### 1. Anteprima restore

Ogni backup, prima di essere applicato, deve essere validato senza modificare il database.

Controlli minimi:

- ZIP valido;
- nessun path traversal;
- manifest presente e coerente;
- formato supportato;
- checksum corretto;
- tabelle appartenenti alla whitelist People;
- schema compatibile;
- UUID validi;
- conteggi plausibili;
- nessun file arbitrario eseguibile.

La preview mostra:

- data backup;
- versione People di origine;
- numero persone;
- persone attive/cestinate;
- righe storico;
- merge;
- ignore duplicati;
- compatibilità;
- eventuali avvisi o blocchi.

### 2. Restore completo

Prima di ogni restore completo:

1. viene creato automaticamente un **backup di sicurezza dello stato corrente**;
2. si richiede conferma esplicita;
3. il restore avviene in transazione dove supportato;
4. si ripristinano esclusivamente le tabelle People previste dal formato;
5. ID e UUID vengono conservati esattamente per mantenere coerenza interna;
6. al termine si esegue una verifica di integrità;
7. se il restore fallisce, si esegue rollback e il database corrente non deve rimanere in stato parziale.

Il restore completo non modifica mai tabelle di altri componenti.

### 3. Restore singola persona

Da un backup valido è possibile ripristinare una singola persona per UUID.

Regole:

- se lo stesso UUID esiste nel cestino, si preferisce il normale Ripristina dal cestino;
- se lo UUID non esiste, si reinserisce la persona preservando UUID;
- se l'ID originale è libero può essere riutilizzato, altrimenti viene assegnato un nuovo ID;
- i riferimenti esterni devono basarsi sull'UUID pubblico, non sull'ID locale;
- prima di sovrascrivere una persona esistente si mostra un confronto e si richiede conferma.

## Log manutenzione

Si introduce `#__xdecaropeople_maintenance_log` per operazioni amministrative non legate a una normale modifica anagrafica.

Campi minimi:

- `id`
- `action`
- `subject_uuid` nullable
- `actor_user_id`
- `created`
- `metadata` JSON/TEXT

Azioni previste:

- `backup_create`
- `backup_download`
- `backup_delete`
- `restore_preview`
- `restore_full`
- `restore_person`
- `trash_person`
- `restore_trash_person`
- `purge_person`
- `integrity_check`

Il log non deve memorizzare copie complete dei dati personali quando non necessarie.

## Nuova pagina Informazioni

La pagina resta `view=information`, ma diventa un centro operativo organizzato in sezioni compatte e responsive.

### A. Prodotto + Ambiente

Mostra almeno:

- People versione
- versione pacchetto
- versione schema
- Joomla
- PHP
- driver database
- versione database
- Core versione e stato
- timezone applicazione
- ambiente compatibile/non compatibile

### B. Database People

Mostra almeno:

- persone totali
- persone attive/pubblicate
- persone non pubblicate
- persone nel cestino
- righe storico
- gruppi merge registrati
- ignore duplicati
- numero backup disponibili
- ultimo backup
- ultimo restore
- stato schema

### C. Componenti collegati

Visualizza disponibilità tramite capability pubbliche, senza query private:

- Core
- Organizations
- Membership
- Competitions
- Photos
- Documents
- Notifications

Per ogni componente: `Collegato`, `Non disponibile` oppure `Capability non supportata`.

Se una capability pubblica espone conteggi utili, People può mostrarli; altrimenti mostra solo lo stato di connessione. Non inventare valori.

### D. Diagnostica e integrità

Controlli almeno su:

- tabelle People presenti;
- colonne/versione schema;
- UUID mancanti o duplicati;
- user_id duplicati incompatibili con il vincolo previsto;
- storico con person_id non più esistente;
- merge con riferimenti interni non risolvibili;
- storage backup scrivibile e privato;
- estensioni PHP richieste per ZIP/JSON;
- ultimo esito controllo integrità.

Ogni voce usa stato chiaro: OK / Avviso / Errore.

### E. Gestione database

Azioni:

- **Crea backup ora**
- **Carica backup**
- **Anteprima restore**
- **Restore completo**
- **Verifica integrità**

Le azioni distruttive non sono mai eseguite con GET.

### F. Backup disponibili

Tabella compatta con:

- data/ora
- versione People
- persone
- dimensione
- checksum breve
- creato da
- stato
- azioni: Scarica, Anteprima, Ripristina, Elimina backup

L'eliminazione del file backup richiede conferma ma non modifica i dati People.

### G. Cancellati

Mostra:

- totale persone nel cestino;
- ultimi record cestinati;
- nome;
- UUID abbreviato;
- data cancellazione se disponibile dal maintenance log;
- autore;
- azioni Ripristina e Apri cestino.

La lista completa usa la vista Persone filtrata su `state = -2`, non una copia separata.

### H. Attività manutenzione

Mostra almeno gli ultimi eventi di backup/restore/cestino/eliminazione definitiva, con data e utente.

## Permessi e sicurezza

Permessi minimi:

- visualizzare Informazioni: `core.manage` sul componente;
- creare/scaricare backup: nuovo permesso `people.backup` oppure `core.admin`;
- restore: nuovo permesso `people.restore` oppure `core.admin`;
- spostare/ripristinare dal cestino: `core.edit.state`;
- eliminare definitivamente: `core.delete` e conferma esplicita.

Tutte le POST usano CSRF token Joomla.

I file caricati per restore devono avere limiti di dimensione configurabili e non vengono mai eseguiti o estratti in directory web pubbliche.

## UX

Principi:

- layout Joomla 6 coerente con la dashboard People 1.7.27;
- schede compatte, niente grafici decorativi;
- numeri e stati leggibili anche su mobile;
- operazioni pericolose separate visivamente;
- Restore completo e Elimina definitivamente usano stile danger;
- prima del restore mostra sempre preview;
- dopo backup/restore mostra risultato con conteggi verificabili;
- non mostrare percorsi filesystem o dettagli sensibili agli utenti senza permesso amministrativo.

## Compatibilità e migrazione

La release deve aggiornare install SQL e SQL update in modo idempotente.

Nuove installazioni e upgrade da versioni precedenti devono produrre lo stesso schema finale.

Il passaggio al cestino non modifica automaticamente record esistenti. Le persone attuali mantengono lo stato corrente.

## Test obbligatori

### Contratti

- manifest e versione 1.7.28 coerenti;
- ACL nuovi presenti;
- schema nuove tabelle presente;
- view Informazioni contiene le sezioni previste;
- nessun accesso SQL diretto a tabelle di altri componenti.

### Backup

- creazione backup con dataset noto;
- checksum deterministico del payload canonico;
- backup contiene solo tabelle whitelist People;
- download autorizzato;
- rifiuto backup corrotto o incompatibile.

### Restore

- preview non modifica dati;
- restore completo ricostruisce dataset identico;
- backup automatico pre-restore creato;
- rollback su errore;
- restore singola persona per UUID;
- nessuna modifica a tabelle esterne.

### Cestino

- trash conserva ID e UUID;
- persona cestinata sparisce dagli elenchi/picker normali;
- restore cestino riporta lo stesso record;
- eliminazione definitiva richiede permesso e conferma;
- log manutenzione registra le operazioni.

### Joomla runtime

- installazione pulita Joomla 6.1.3;
- upgrade da baseline supportate;
- dashboard People ancora funzionante;
- Duplicati, Import, Export e merge non regressivi;
- PHP 8.3+.

## Fuori scope per 1.7.28

- backup del sito Joomla intero;
- backup di database appartenenti ad altri componenti;
- scheduler automatico/cron dei backup;
- sincronizzazione cloud remota;
- cifratura server-side con gestione chiavi avanzata;
- restore automatico di Organizations/Membership/Competitions/Photos.

Queste funzionalità potranno essere aggiunte in release successive senza cambiare il formato base del backup People, purché versionato.

## Criteri di accettazione

La release è accettabile quando un amministratore può:

1. aprire Informazioni e vedere uno stato completo e reale di People;
2. creare e scaricare un backup People verificato;
3. vedere l'elenco dei backup disponibili;
4. caricare/selezionare un backup e ottenere una preview senza modifiche;
5. eseguire restore completo con backup automatico pre-restore;
6. cestinare una persona senza perderne ID/UUID;
7. ripristinarla dal cestino;
8. eliminare definitivamente solo con azione esplicita e autorizzata;
9. consultare gli ultimi eventi di manutenzione;
10. eseguire una diagnostica di integrità;
11. mantenere intatti dashboard, duplicati, import/export, merge e contratti pubblici People.
