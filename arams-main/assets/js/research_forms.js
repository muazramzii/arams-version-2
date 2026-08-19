// ============================================================
//  ARAMS — Shared research-record form builders + helpers
//  Used by: pages/lecturer/research.php (lecturer add)
//           pages/admin/lecturer_detail.php (admin add on behalf)
// ============================================================

// ── Grant Level -> Type cascading map (matches FRT) ──────────
const GRANT_TYPES = {
    'Universiti':   ['Tier 1','RE-GG','Contract','GPPS','GPP','ICI','UTHM Internal (VoT)'],
    'National':     ['Geran Tanpa Dana (X)','FRGS','PRGS','TRGS','LRGS','Geran Kontrak Kementerian','Lain-Lain Geran Kebangsaan','KKP','PPRN','Sepadan RESIP','Sepadan MTUN'],
    'International': ['International'],
    'NGO':          ['NGO'],
    'Industries':   ['Industries']
};
function cascadeGrantType(level, selected) {
    const sel = document.getElementById('grantCategorySelect');
    if (!sel) return;
    const list = GRANT_TYPES[level] || [];
    if (!list.length) {
        sel.innerHTML = '<option value="">— Select Grant Level first —</option>';
        return;
    }
    sel.innerHTML = list.map(t => `<option value="${t}"${selected===t?' selected':''}>${t}</option>`).join('');
}

// ── Full country list (ISO) for publication country dropdown ─
const COUNTRIES = ["Afghanistan","Albania","Algeria","Andorra","Angola","Antigua and Barbuda","Argentina","Armenia","Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan","Bolivia","Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi","Cabo Verde","Cambodia","Cameroon","Canada","Central African Republic","Chad","Chile","China","Colombia","Comoros","Congo","Costa Rica","Croatia","Cuba","Cyprus","Czech Republic","Denmark","Djibouti","Dominica","Dominican Republic","Ecuador","Egypt","El Salvador","Equatorial Guinea","Eritrea","Estonia","Eswatini","Ethiopia","Fiji","Finland","France","Gabon","Gambia","Georgia","Germany","Ghana","Greece","Grenada","Guatemala","Guinea","Guinea-Bissau","Guyana","Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq","Ireland","Israel","Italy","Ivory Coast","Jamaica","Japan","Jordan","Kazakhstan","Kenya","Kiribati","Kosovo","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia","Libya","Liechtenstein","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta","Marshall Islands","Mauritania","Mauritius","Mexico","Micronesia","Moldova","Monaco","Mongolia","Montenegro","Morocco","Mozambique","Myanmar","Namibia","Nauru","Nepal","Netherlands","New Zealand","Nicaragua","Niger","Nigeria","North Korea","North Macedonia","Norway","Oman","Pakistan","Palau","Palestine","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Poland","Portugal","Qatar","Romania","Russia","Rwanda","Saint Kitts and Nevis","Saint Lucia","Saint Vincent and the Grenadines","Samoa","San Marino","Sao Tome and Principe","Saudi Arabia","Senegal","Serbia","Seychelles","Sierra Leone","Singapore","Slovakia","Slovenia","Solomon Islands","Somalia","South Africa","South Korea","South Sudan","Spain","Sri Lanka","Sudan","Suriname","Sweden","Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand","Timor-Leste","Togo","Tonga","Trinidad and Tobago","Tunisia","Turkey","Turkmenistan","Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States","Uruguay","Uzbekistan","Vanuatu","Vatican City","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"];
const COUNTRY_OPTS = '<option value="">— Select Country —</option>' +
    COUNTRIES.map(c => `<option value="${c}"${c==='Malaysia'?' selected':''}>${c}</option>`).join('');

function pubForm() { return `<form id="addForm" method="POST">
    <div class="form-group"><label class="form-label">Publication Title *</label>
    <input class="form-control" name="title" required placeholder="Full publication title"></div>
    <div class="form-group"><label class="form-label">Authors</label>
    <input class="form-control" name="authors" placeholder="Author 1, Author 2, ..."></div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Journal / Conference *</label>
        <input class="form-control" name="journal_name" required></div>
        <div class="form-group"><label class="form-label">Year *</label>
        <input class="form-control" name="pub_year" type="number" value="${new Date().getFullYear()}" min="1990" max="2030" required></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Author Role</label>
        <select class="form-control" name="author_role">
            <option>UTHM - First Author</option>
            <option>Corresponding Author</option>
            <option>Penulis Dalam Bab</option>
            <option>Editor</option>
            <option selected>Co-Author</option>
        </select></div>
        <div class="form-group"><label class="form-label">Indexing</label>
        <select class="form-control" name="indexing_type">
            <option>Scopus</option><option>WoS</option><option>Scopus,WoS</option><option>MyCite</option><option>Others</option>
        </select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Quartile</label>
        <select class="form-control" name="quartile">
            <option>Q1</option><option>Q2</option><option>Q3</option><option>Q4</option><option selected>N/A</option>
        </select></div>
        <div class="form-group"><label class="form-label">Type</label>
        <select class="form-control" name="pub_type">
            <option>Journal</option>
            <option>Proceeding / Seminar</option>
            <option>Book Chapter</option>
            <option>Book</option>
            <option>Others</option>
        </select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Impact Factor</label>
        <input class="form-control" name="impact_factor" type="number" step="0.001" min="0" placeholder="e.g. 3.245 (WoS)"></div>
        <div class="form-group"><label class="form-label">Country</label>
        <select class="form-control" name="country">${COUNTRY_OPTS}</select></div>
    </div>
    <div class="form-group"><label class="form-label">DOI</label>
    <input class="form-control" name="doi" placeholder="e.g. 10.1109/..."></div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Student as Author?</label>
        <select class="form-control" name="student_author">
            <option value="0">No</option><option value="1">Yes</option>
        </select></div>
        <div class="form-group"><label class="form-label">Industries Collaboration?</label>
        <select class="form-control" name="industries_collaboration">
            <option value="0">No</option><option value="1">Yes</option>
        </select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">National Collaboration?</label>
        <select class="form-control" name="national_collaboration">
            <option value="0">No</option><option value="1">Yes</option>
        </select></div>
        <div class="form-group"><label class="form-label">International Collaboration?</label>
        <select class="form-control" name="international_collaboration">
            <option value="0">No</option><option value="1">Yes</option>
        </select></div>
    </div>
</form>`; }

function grantForm() { return `<form id="addForm" method="POST">
    <div class="form-group"><label class="form-label">Grant Title *</label>
    <input class="form-control" name="grant_title" required></div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Grant Code</label>
        <input class="form-control" name="grant_code" placeholder="e.g. FRGS/1/2024/..."></div>
        <div class="form-group"><label class="form-label">Funder</label>
        <input class="form-control" name="funder" placeholder="e.g. MOHE, MOSTI"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Role</label>
        <select class="form-control" name="role"><option>PI</option><option>Co-I</option><option>Member</option></select></div>
        <div class="form-group"><label class="form-label">Amount (RM)</label>
        <input class="form-control" name="amount" type="number" step="0.01" min="0"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Start Date</label>
        <input class="form-control" name="start_date" type="date"></div>
        <div class="form-group"><label class="form-label">End Date</label>
        <input class="form-control" name="end_date" type="date"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Grant Level</label>
        <select class="form-control" name="grant_level" id="grantLevelSelect" onchange="cascadeGrantType(this.value)">
            <option value="">— Select —</option>
            <option>Universiti</option>
            <option>National</option>
            <option>International</option>
            <option>NGO</option>
            <option>Industries</option>
        </select></div>
        <div class="form-group"><label class="form-label">Status</label>
        <select class="form-control" name="grant_status">
            <option>Active</option><option>Completed</option><option>Pending Approval</option>
        </select></div>
    </div>
    <div class="form-group"><label class="form-label">Grant Type / Category</label>
    <select class="form-control" name="grant_category" id="grantCategorySelect">
        <option value="">— Select Grant Level first —</option>
    </select></div>
</form>`; }

function hindexForm() { return `<form id="addForm" method="POST">
    <div class="form-row">
        <div class="form-group"><label class="form-label">Year *</label>
        <input class="form-control" name="record_year" type="number" value="${new Date().getFullYear()}" required></div>
        <div class="form-group"><label class="form-label">H-Index Value *</label>
        <input class="form-control" name="hindex_value" type="number" min="0" required></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Total Citations</label>
        <input class="form-control" name="citation_count" type="number" min="0"></div>
        <div class="form-group"><label class="form-label">Source</label>
        <select class="form-control" name="source"><option>Scopus</option><option>WoS</option><option>Google Scholar</option></select></div>
    </div>
    <div class="alert alert-info" style="font-size:12px"><i class="fas fa-info-circle"></i> Upload a screenshot from Scopus/WoS as proof. Admin will verify before approval.</div>
</form>`; }

function ipForm() { return `<form id="addForm" method="POST">
    <div class="form-group"><label class="form-label">IP Title *</label>
    <input class="form-control" name="ip_title" required></div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Type</label>
        <select class="form-control" name="ip_type"><option>Patent</option><option>Copyright</option><option>Trademark</option><option>Industrial Design</option></select></div>
        <div class="form-group"><label class="form-label">IP Number (MyIPO)</label>
        <input class="form-control" name="ip_number" placeholder="e.g. PI2024XXXXXX"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Filing Date</label>
        <input class="form-control" name="filing_date" type="date"></div>
        <div class="form-group"><label class="form-label">Grant / Approval Date</label>
        <input class="form-control" name="grant_date" type="date"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Country</label>
        <select class="form-control" name="country">
            <option>Afghanistan</option><option>Albania</option><option>Algeria</option><option>Argentina</option><option>Australia</option><option>Austria</option><option>Bangladesh</option><option>Belgium</option><option>Brazil</option><option>Brunei</option><option>Cambodia</option><option>Canada</option><option>Chile</option><option>China</option><option>Colombia</option><option>Denmark</option><option>Egypt</option><option>Finland</option><option>France</option><option>Germany</option><option>Greece</option><option>Hong Kong</option><option>India</option><option>Indonesia</option><option>Iran</option><option>Iraq</option><option>Ireland</option><option>Italy</option><option>Japan</option><option>Jordan</option><option>Kenya</option><option>Kuwait</option><option>Laos</option><option selected>Malaysia</option><option>Maldives</option><option>Mexico</option><option>Myanmar</option><option>Nepal</option><option>Netherlands</option><option>New Zealand</option><option>Nigeria</option><option>Norway</option><option>Oman</option><option>Pakistan</option><option>Philippines</option><option>Poland</option><option>Portugal</option><option>Qatar</option><option>Russia</option><option>Saudi Arabia</option><option>Singapore</option><option>South Africa</option><option>South Korea</option><option>Spain</option><option>Sri Lanka</option><option>Sweden</option><option>Switzerland</option><option>Taiwan</option><option>Thailand</option><option>Turkey</option><option>United Arab Emirates</option><option>United Kingdom</option><option>United States</option><option>Vietnam</option><option>Yemen</option>
        </select></div>
    </div>
    <div class="form-group"><label class="form-label">Registration Status</label>
    <select class="form-control" name="registration_status"><option>Filed</option><option>Awarded</option></select></div>
</form>`; }

function incomeForm() { return `<form id="addForm" method="POST">
    <div class="form-group"><label class="form-label">Income Source *</label>
    <input class="form-control" name="source" required placeholder="e.g. MOHE Research Grant, Industry Collaboration"></div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Amount (RM) *</label>
        <input class="form-control" name="amount" type="number" step="0.01" min="0" required></div>
        <div class="form-group"><label class="form-label">Year Received *</label>
        <input class="form-control" name="year_received" type="number" value="${new Date().getFullYear()}" required></div>
    </div>
    <div class="form-group"><label class="form-label">Category</label>
    <select class="form-control" name="income_category">
        <option>Research Grant</option><option>Consultancy</option><option>Contract Research</option>
        <option>Commercialisation</option><option>Training</option>
        <option>Endowment</option><option>In-Kind</option><option>Others</option>
    </select></div>
</form>`; }