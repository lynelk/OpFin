@extends('layouts.app')

@section('title', 'About Allan Abaho - ' . config('app.name'))

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h1 class="h4 mb-0">About Allan Abaho</h1>
                    </div>

                    <div class="card-body">
                        <p class="text-muted">Last updated: {{ now()->format('F j, Y') }}</p>

                        <div class="profile-content">
                            <h2 class="h5 mt-4">1. Introduction</h2>
                            <p>
                                Allan Abaho is a software engineer with a strong focus on building scalable,
                                secure, and user-centric digital products. He specializes in backend systems,
                                APIs, and data-driven applications, with experience across fintech, web platforms,
                                and modern cloud-based architectures.
                            </p>

                            <h2 class="h5 mt-4">2. Professional Background</h2>
                            <p>
                                Allan has worked on a variety of software systems ranging from internal business tools
                                to customer-facing applications. His work often involves designing clean system
                                architectures, integrating third-party services, and ensuring performance,
                                reliability, and maintainability.
                            </p>

                            <h2 class="h5 mt-4">3. Technical Skills</h2>
                            <p>His core technical competencies include:</p>
                            <ul>
                                <li>Backend development with PHP (Laravel)</li>
                                <li>RESTful API design and integration</li>
                                <li>Database design and optimization (MySQL, PostgreSQL)</li>
                                <li>Authentication, authorization, and security best practices</li>
                                <li>Building AI-powered features using embeddings and retrieval-based systems</li>
                                <li>Version control and collaborative development using Git</li>
                            </ul>

                            <h2 class="h5 mt-4">4. Areas of Interest</h2>
                            <p>
                                Allan is particularly interested in financial technology, artificial intelligence,
                                and developer tooling. He enjoys working on systems that simplify complex workflows,
                                improve decision-making, and deliver real-world value to users.
                            </p>

                            <h2 class="h5 mt-4">5. Development Philosophy</h2>
                            <p>
                                He believes in writing clean, readable code, prioritizing long-term maintainability,
                                and solving problems pragmatically. Allan values thoughtful system design, clear
                                documentation, and continuous learning as essential parts of professional growth.
                            </p>

                            <h2 class="h5 mt-4">6. Collaboration & Work Style</h2>
                            <p>
                                Allan works well both independently and within teams. He emphasizes clear
                                communication, well-defined requirements, and iterative improvement when
                                collaborating with designers, product managers, and other engineers.
                            </p>

                            <h2 class="h5 mt-4">7. Contact</h2>
                            <p>
                                For professional inquiries or collaboration opportunities, Allan can be reached
                                through the platform administrators or via official contact channels associated
                                with this application.
                            </p>

                            <address>
                                {{ config('app.name') }}<br>
                                Kampala, Uganda
                            </address>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
