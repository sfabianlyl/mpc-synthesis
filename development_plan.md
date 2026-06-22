# Synthesis Web App Development Plan

## 1. System Architecture & Tech Stack Evaluation

**Frontend:**
* **Framework:** React with Vite.
* **Routing & State:** React Router for the three distinct interfaces. Zustand or Redux for state management, particularly for holding the complex synthesized data in the Master interface.

**Backend & Framework Alternatives:**
* **Laravel (PHP)::** Highly suitable for rapid development, and its ORM [Object-Relational Mapping] is excellent for relational data. Deployment is universally supported.
* **Alternative 1 - Elixir (Phoenix):** Leveraging an Elixir-based architecture provides superior handling of concurrent connections. This is highly advantageous for streaming LLM responses via WebSockets to the frontend without blocking threads, mirroring architectural patterns utilized in scalable systems like the Oblatio framework.
* **Alternative 2 - FastAPI (Python):** Native compatibility with Python AI ecosystems makes building the LLM integration and failover logic significantly easier.

**Database:**
* **PostgreSQL:** Native support for JSONB [a data format that stores JSON data in a binary representation] is highly useful for dynamic survey responses or LLM output caching. It handles the complex relational queries needed to link the 94 groups to their respective synthesis members efficiently.

**LLM Infrastructure & Failover:**
* **Primary (Local/LAN):** A local inference server running Ollama or vLLM on a dedicated machine. Business-grade mini PCs like a Lenovo ThinkCentre or ASUS ExpertCenter provide excellent, stable compute for these analysis tasks. The backend connects to this via a local IP or a Tailscale mesh network.
* **Secondary (Cloud Failover):** Google Gemini API.
* **Failover Logic:** [Inference] The backend API wrapper must implement a strict timeout (e.g., 5-10 seconds) or connection-refused catch block for the local endpoint to prevent the application from hanging. If triggered, the request is immediately rerouted to the Gemini API using the same standardized prompt.

## 2. Interface Specifications (Mapping to CIS Phases)

### Interface 1: Responder Portal (Phase 1)
* **Target Users:** Group Facilitators.
* **Functionality:** A minimalist, mobile-responsive single-field form.
* **Input:** The "consensus-paragraph" (limited to ~10 sentences)[cite: 84].
* **Data Flow:** Submissions are tagged with the specific group ID and immediately routed to the database, making them visible to the assigned Secretary[cite: 85].

### Interface 2: Secretary Dashboard (Phase 2)
* **Target Users:** Synthesis Team (e.g., 1 Secretary per assigned groups)[cite: 87].
* **Functionality:**
    * Displays the raw consensus paragraphs from assigned groups.
    * **LLM Integration:** A button triggers the LLM to pre-process the paragraphs, extracting data into the three mandatory subcategories: (A) Convergence, (B) Matters for Consideration, and (C) Proposals[cite: 88, 89, 90].
    * **Review & Edit:** The Secretary reviews the AI-generated tables, graphs, and summaries, manually adjusting the text before submitting it for compilation[cite: 91, 92].

### Interface 3: Master Synthesis & Drafting App (Phase 3 & 4)
* **Target Users:** Drafting Team / Discernment Team.
* **Functionality:**
    * **Drill-Down Capability:** Displays the aggregated synthesis from all Secretaries, with clickable links expanding to reveal the original Responder paragraphs.
    * **Global Synthesis:** Triggers a macro-level LLM prompt to identify overarching "emerging themes" across all reports[cite: 136, 137].
    * **Export/Booklet Creation:** Compiles the structured data (Introduction, Emerging Themes, Conclusion) [cite: 144, 145, 149] into a PDF or markdown booklet using a frontend PDF library or backend generation.

## 3. Database Schema Blueprint

* `users`: id, role (responder, secretary, drafter), credentials.
* `groups`: id, name, secretary_id (foreign key).
* `responses`: id, group_id, consensus_text, submitted_at.
* `synthesis_reports`: id, secretary_id, convergence_text, consideration_text, proposals_text, llm_generated_flag.
* `final_documents`: id, content_json, exported_file_path.

## 4. Development Roadmap

1.  **Phase 1: Environment Setup (Weeks 1-2)**
    * Initialize Vite/React frontend and backend framework.
    * Deploy PostgreSQL and run initial migrations.
    * Setup Tailscale network between the development machine and the local LLM hardware.
2.  **Phase 2: Core Routing & Responder Interface (Week 3)**
    * Build authentication and role-based access control.
    * Develop the single-field submission form and backend ingestion API.
3.  **Phase 3: LLM Integration & Secretary Dashboard (Weeks 4-5)**
    * Deploy local LLM server and test prompt engineering for text extraction.
    * Implement the failover service class to switch to Gemini API upon local failure.
    * Build the Secretary review UI with data tables.
4.  **Phase 4: Master Synthesis & Export (Weeks 6-7)**
    * Develop the macro-dashboard for the Drafting Team.
    * Implement data visualization (graphs for emerging themes).
    * Integrate PDF/Booklet generation logic.
5.  **Phase 5: Testing (Week 8)**
    * Simulate concurrent submissions and test LLM queue management.

## 5. System Flowchart

```mermaid
flowchart TD
    subgraph Frontend
        A[Responder Interface]
        B[Secretary Dashboard]
        C[Master Drafting App]
    end

    subgraph Backend
        D[Auth & Routing]
        E[Data Controllers]
        F[LLM Failover Service]
    end

    subgraph Database
        G[(PostgreSQL DB)]
    end

    subgraph LLM_Infrastructure
        H[Local LLM via Tailscale]
        I[Google Gemini API]
    end

    A -->|Submits Consensus| D
    D --> E
    E -->|Reads/Writes| G
    B <-->|Requests Analysis| D
    D <--> F
    F -->|Primary Request| H
    F -->|Failover Request| I
    C <-->|Aggregates Data| D


