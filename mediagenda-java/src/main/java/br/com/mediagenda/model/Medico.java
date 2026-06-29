package br.com.mediagenda.model;

import javax.persistence.*;

@Entity
@Table(name = "medicos")
public class Medico{
    //Argumentos
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)    
    private Long id;

    private String nome;
    private String crm;
    
    @Column(name="especialidade_id")
    private Long especialidadeId;

    private String email;
    //Construtor
    public Medico(){}
    public Medico(Long id, String nome, String crm, Long especialidadeId, String email){
        this.id = id;
        this.nome = nome;
        this.crm = crm;
        this.especialidadeId = especialidadeId;
        this.email = email;
    }
    public String getEmail(){
        return this.email;
    }
    public void setEmail(String email){
        this.email = email;
    }

    public Long getId(){
        return this.id;
    }
    public void setId(Long id){
        this.id = id;
    }
    public String getNome(){
        return this.nome;
    }
    public void setNome(String nome){
        this.nome = nome;
    }
    public String getCrm(){
        return this.crm;
    }
    public void setCrm(String crm){
        this.crm = crm;
    }
    public Long getEspecialidadeId(){
        return this.especialidadeId;
    }
    public void setEspecialidadeId(Long especialidadeId){
        this.especialidadeId = especialidadeId;
    }


}