// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import { createRoot } from "react-dom/client";
import React, { ChangeEvent } from "react";
import { getTranslations } from "../functions/translations";
import { InputInterface } from "../interface/InputInterface";
import notEmptyValidator from "../functions/form";

const containerSelector = "#sage_user";
const siteUrl = $("[data-sage-site-url]").attr("data-sage-site-url");
const wpnonce = $("[data-sage-nonce]").attr("data-sage-nonce");
let translations: any = getTranslations();
let currentCtNumSearch = "";
const user = JSON.parse($("[data-sage-user]").attr("data-sage-user") ?? "null");
const userMetaWordpress = JSON.parse(
  $("[data-sage-user-meta-wordpress]").attr("data-sage-user-meta-wordpress") ??
    "null",
);
const pCattarifs: any[] = JSON.parse(
  $("[data-sage-pcattarifs]").attr("data-sage-pcattarifs") ?? "[]",
);
const pCatComptas: any[] = JSON.parse(
  $("[data-sage-pcatcomptas]").attr("data-sage-pcatcomptas") ?? "[]",
).Ven;

interface FormState {
  creationType: InputInterface;
  autoGenerateCtNum: InputInterface;
  ctNum: InputInterface;
}

interface FormState2 {
  nCatTarif: InputInterface;
}

interface FormState3 {
  nCatCompta: InputInterface;
}

interface State {
  fComptet: any | undefined;
}

const UserNCatComptasComponent: React.FC<State> = ({ fComptet }) => {
  const getDefaultValue = React.useCallback((): FormState3 => {
    let nCatCompta = "";
    if (fComptet) {
      nCatCompta = fComptet.nCatCompta.toString();
    } else {
      for (const key in pCatComptas) {
        nCatCompta = pCatComptas[key].cbIndice;
        break;
      }
    }
    return {
      nCatCompta: { value: nCatCompta },
    };
  }, []);
  const [values, setValues] = React.useState<FormState3>(getDefaultValue());
  const handleChangeSelect =
    (prop: keyof FormState3) => (event: ChangeEvent<HTMLSelectElement>) => {
      setValues((v) => {
        return {
          ...v,
          [prop]: {
            ...v[prop],
            value: event.target.value as string,
            error: "",
          },
        };
      });
    };

  let labelSage = "";
  if (fComptet) {
    for (const key in pCatComptas) {
      if (pCatComptas[key].cbIndice === fComptet.nCatCompta) {
        labelSage = pCatComptas[key].label;
        break;
      }
    }
  }
  return (
    <tr>
      <th>
        <label htmlFor="_sage_nCatCompta">Catégorie comptable</label>
      </th>
      <td>
        <select
          name="_sage_nCatCompta"
          id="_sage_nCatCompta"
          value={values.nCatCompta.value}
          onChange={handleChangeSelect("nCatCompta")}
        >
          {Object.entries(pCatComptas).map((data) => {
            const pCatCompta: any = data[1];
            return (
              <option key={pCatCompta.cbIndice} value={pCatCompta.cbIndice}>
                {pCatCompta.label}
              </option>
            );
          })}
        </select>
        {user &&
          fComptet &&
          fComptet.nCatCompta.toString() !== values.nCatCompta.value && (
            <div>
              <span className="error-message">
                La catégorie tarifaire renseignée dans Sage est différente de
                celle renseignée dans Wordpress.
              </span>
              <br />
              <span>
                Valeur dans Sage: <strong>{labelSage}</strong>
              </span>
            </div>
          )}
      </td>
    </tr>
  );
};

const UserNCatTarifComponent: React.FC<State> = ({ fComptet }) => {
  const getDefaultValue = React.useCallback((): FormState2 => {
    let nCatTarif = "";
    if (fComptet) {
      nCatTarif = fComptet.nCatTarif.toString();
    } else {
      for (const key in pCattarifs) {
        nCatTarif = pCattarifs[key].cbIndice;
        break;
      }
    }
    return {
      nCatTarif: { value: nCatTarif },
    };
  }, []);
  const [values, setValues] = React.useState<FormState2>(getDefaultValue());
  const handleChangeSelect =
    (prop: keyof FormState2) => (event: ChangeEvent<HTMLSelectElement>) => {
      setValues((v) => {
        return {
          ...v,
          [prop]: {
            ...v[prop],
            value: event.target.value as string,
            error: "",
          },
        };
      });
    };

  let labelSage = "";
  if (fComptet) {
    for (const key in pCattarifs) {
      if (pCattarifs[key].cbIndice === fComptet.nCatTarif) {
        labelSage = pCattarifs[key].ctIntitule;
        break;
      }
    }
  }
  return (
    <tr>
      <th>
        <label htmlFor="_sage_nCatTarif">Catégorie tarifaire</label>
      </th>
      <td>
        <select
          name="_sage_nCatTarif"
          id="_sage_nCatTarif"
          value={values.nCatTarif.value}
          onChange={handleChangeSelect("nCatTarif")}
        >
          {Object.entries(pCattarifs).map((data) => {
            const pCattarif: any = data[1];
            return (
              <option key={pCattarif.cbIndice} value={pCattarif.cbIndice}>
                {pCattarif.ctIntitule}
              </option>
            );
          })}
        </select>
        {user &&
          fComptet &&
          fComptet.nCatTarif.toString() !== values.nCatTarif.value && (
            <div>
              <span className="error-message">
                La catégorie tarifaire renseignée dans Sage est différente de
                celle renseignée dans Wordpress.
              </span>
              <br />
              <span>
                Valeur dans Sage: <strong>{labelSage}</strong>
              </span>
            </div>
          )}
      </td>
    </tr>
  );
};

const UserComponent = () => {
  const getDefaultValue = React.useCallback((): FormState => {
    let ctNum = "";
    if (
      userMetaWordpress?._sage_ctNum &&
      userMetaWordpress?._sage_ctNum.length > 0
    ) {
      ctNum = userMetaWordpress?._sage_ctNum[0];
    }
    return {
      ctNum: { value: ctNum },
      autoGenerateCtNum: { value: true },
      creationType: { value: "new" },
    };
  }, []);
  const [values, setValues] = React.useState<FormState>(getDefaultValue());
  const [loadingSearchFComptet, setLoadingSearchFComptet] =
    React.useState<boolean>(false);
  const [fComptet, setFComptet] = React.useState<any | undefined>(undefined);
  const [thisUser, setThisUser] = React.useState<any | undefined>(undefined);

  const handleChange =
    (prop: keyof FormState) => (event: React.ChangeEvent<HTMLInputElement>) => {
      setValues((v) => {
        return {
          ...v,
          [prop]: { ...v[prop], value: event.target.value, error: "" },
        };
      });
    };

  const handleChangeRadio =
    (prop: keyof FormState) => (event: ChangeEvent<HTMLInputElement>) => {
      setValues((v) => {
        return {
          ...v,
          [prop]: { ...v[prop], value: event.target.value, error: "" },
        };
      });
    };

  const handleChangeCheckbox =
    (prop: keyof FormState) => (event: React.ChangeEvent<HTMLInputElement>) => {
      setValues((v) => {
        return {
          ...v,
          [prop]: { ...v[prop], value: event.target.checked, error: "" },
        };
      });
    };

  const getFComptet = async () => {
    const ctNum = values.ctNum.value.replaceAll(" ", "");
    if (ctNum === "") {
      return;
    }
    setLoadingSearchFComptet(true);
    currentCtNumSearch = ctNum;
    const response = await fetch(
      siteUrl +
        "/index.php?rest_route=" +
        encodeURI("/sage/v1/user/" + ctNum) +
        "&_wpnonce=" +
        wpnonce,
    );
    if (response.status === 200) {
      if (currentCtNumSearch === ctNum) {
        const data = await response.json();
        setFComptet(data.fComptet);
        setThisUser(data.user);
      }
    } else {
      // todo toastr
    }
    if (currentCtNumSearch === ctNum) {
      setLoadingSearchFComptet(false);
    }
  };

  const validCtNum =
    (fComptet && values.creationType.value === "link") ||
    (values.creationType.value === "new" &&
      (fComptet === null || values.autoGenerateCtNum.value));
  const notValidCtNum =
    (fComptet && values.creationType.value === "new") ||
    (fComptet === null && values.creationType.value === "link");
  const showCtNumField =
    !!user ||
    values.creationType.value === "link" ||
    (values.creationType.value === "new" && !values.autoGenerateCtNum.value);
  const showSageForm =
    !!user ||
    ((values.creationType.value === "link" ||
      values.creationType.value === "new") &&
      validCtNum);

  const validateForm = (): boolean => {
    let result = notValidCtNum || notEmptyValidator(values.ctNum.value) !== "";
    if (result) {
      setValues((v) => {
        v.ctNum.error = "notValid";
        return {
          ...v,
        };
      });
    }
    return !result;
  };

  React.useEffect(() => {
    const timeoutTyping = setTimeout(() => {
      getFComptet();
    }, 500);
    return () => clearTimeout(timeoutTyping);
  }, [values.ctNum.value]); // eslint-disable-line react-hooks/exhaustive-deps

  React.useEffect(() => {
    setFComptet(undefined);
    setThisUser(undefined);
  }, [values.ctNum.value]); // eslint-disable-line react-hooks/exhaustive-deps

  React.useEffect(() => {
    if (
      (values.autoGenerateCtNum.value && values.creationType.value === "new") ||
      values.creationType.value === "none"
    ) {
      setValues((v) => {
        v.ctNum.value = "";
        v.ctNum.error = "";
        return { ...v };
      });
      setFComptet(undefined);
      setThisUser(undefined);
    }
  }, [values.autoGenerateCtNum.value, values.creationType.value]); // eslint-disable-line react-hooks/exhaustive-deps

  React.useEffect(() => {
    $(document).on("submit", 'form[name="createuser"]', (e) => {
      if (!validateForm()) {
        e.preventDefault();
      }
    });
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  console.log(values.ctNum.error);
  return (
    <table className="form-table" role="presentation">
      <tbody>
        <tr>
          <th style={{ padding: 0 }}>
            <h2>Sage</h2>
          </th>
        </tr>
        {!user && (
          <>
            <tr>
              <th>
                <label htmlFor="_sage_creationType">Type d'utilisateur</label>
              </th>
              <td>
                <label htmlFor="_sage_creationType_none">
                  <input
                    type="radio"
                    name="sage_radio_buttons"
                    value="none"
                    id="_sage_creationType_none"
                    checked={values.creationType.value === "none"}
                    onChange={handleChangeRadio("creationType")}
                  />
                  Ne pas créer de compte Sage
                </label>
                <br />
                <br />
                <label htmlFor="_sage_creationType_new">
                  <input
                    type="radio"
                    name="sage_radio_buttons"
                    value="new"
                    id="_sage_creationType_new"
                    checked={values.creationType.value === "new"}
                    onChange={handleChangeRadio("creationType")}
                  />
                  Créer un nouveau compte Sage
                </label>
                <br />
                <br />
                <label htmlFor="_sage_creationType_link">
                  <input
                    type="radio"
                    name="sage_radio_buttons"
                    value="link"
                    id="_sage_creationType_link"
                    checked={values.creationType.value === "link"}
                    onChange={handleChangeRadio("creationType")}
                  />
                  Lier à un compte Sage déjà existant
                </label>
              </td>
            </tr>
            {values.creationType.value === "new" && (
              <tr>
                <th>
                  <label htmlFor="_sage_auto_generate_ctnum">
                    Générer le code client
                  </label>
                </th>
                <td>
                  <input
                    type="checkbox"
                    name="_sage_auto_generate_ctnum"
                    id="_sage_auto_generate_ctnum"
                    value="1"
                    checked={values.autoGenerateCtNum.value}
                    onChange={handleChangeCheckbox("autoGenerateCtNum")}
                  />
                  <label htmlFor="_sage_auto_generate_ctnum">
                    Laisser l'API Sage générer le code client automatiquement.
                  </label>
                </td>
              </tr>
            )}
          </>
        )}
        {showCtNumField && (
          <tr className="form-field">
            <th>
              <label htmlFor="_sage_ctNum">
                {translations.fComptets.ctNum}
              </label>
            </th>
            <td>
              <div style={{ position: "relative" }}>
                <input
                  type="text"
                  name="_sage_ctNum"
                  id="_sage_ctNum"
                  readOnly={!!user}
                  style={{
                    ...(values.ctNum.error !== "" && {
                      borderColor: "#d63638",
                    }),
                  }}
                  value={values.ctNum.value}
                  onChange={handleChange("ctNum")}
                />
                {loadingSearchFComptet && (
                  <svg className="svg-spinner" viewBox="0 0 50 50">
                    <circle
                      className="path"
                      cx="25"
                      cy="25"
                      r="20"
                      fill="none"
                      stroke-width="5"
                    ></circle>
                  </svg>
                )}
                {validCtNum && (
                  <span
                    className="dashicons dashicons-yes endDashiconsInput"
                    style={{ color: "green" }}
                  ></span>
                )}
                {notValidCtNum && (
                  <>
                    <span
                      className="dashicons dashicons-no endDashiconsInput"
                      style={{ color: "red" }}
                    ></span>
                    {values.creationType.value === "link" && (
                      <span>Ce compte Sage n'existe pas</span>
                    )}
                    {values.creationType.value === "new" && (
                      <>
                        <span>
                          Ce compte Sage existe déjà{" "}
                          {thisUser && (
                            <a
                              href={
                                siteUrl +
                                "/wp-admin/user-edit.php?user_id=" +
                                thisUser.ID
                              }
                            >
                              {thisUser.data.display_name}
                            </a>
                          )}
                        </span>
                      </>
                    )}
                  </>
                )}
              </div>
            </td>
          </tr>
        )}
        {showSageForm && (
          <>
            <UserNCatTarifComponent fComptet={fComptet} />
            <UserNCatComptasComponent fComptet={fComptet} />
          </>
        )}
      </tbody>
    </table>
  );
};

const dom = document.querySelector(containerSelector);
if (dom) {
  const root = createRoot(dom);
  root.render(<UserComponent />);
}
