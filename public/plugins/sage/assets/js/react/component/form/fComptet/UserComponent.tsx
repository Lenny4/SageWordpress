// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import { createRoot } from "react-dom/client";
import React, { ChangeEvent } from "react";
import { getTranslations } from "../../../../functions/translations";
import { InputInterface } from "../../../../interface/InputInterface";
import { stringValidator } from "../../../../functions/validator";
import { TOKEN } from "../../../../token";

const containerSelector = `#${TOKEN}_user`;
const siteUrl = $(`[data-${TOKEN}-site-url]`).attr(`data-${TOKEN}-site-url`);
const wpnonce = $(`[data-${TOKEN}-nonce]`).attr(`data-${TOKEN}-nonce`);
const autoCreateSageFcomptet =
  $(`[data-${TOKEN}-auto-create-${TOKEN}-fcomptet]`).attr(
    `data-${TOKEN}-auto-create-${TOKEN}-fcomptet`,
  ) === "on";
let translations: any = getTranslations();
let currentCtNumSearch = "";
const user = JSON.parse(
  $(`[data-${TOKEN}-user]`).attr(`data-${TOKEN}-user`) ?? "null",
);
const userMetaWordpress = JSON.parse(
  $(`[data-${TOKEN}-user-meta-wordpress]`).attr(
    `data-${TOKEN}-user-meta-wordpress`,
  ) ?? "null",
);
const pCattarifs: any[] = JSON.parse(
  $(`[data-${TOKEN}-pcattarifs]`).attr(`data-${TOKEN}-pcattarifs`) ?? "[]",
);
const pCatComptas: any[] = JSON.parse(
  $(`[data-${TOKEN}-pcatcomptas]`).attr(`data-${TOKEN}-pcatcomptas`) ?? "[]",
).Ven;

interface FormState {
  creationType: InputInterface;
  autoGenerateCtNum: InputInterface;
  ctNum: InputInterface;
}

interface FormState2 {
  nCompta: InputInterface;
}

interface State {
  fComptet: any | undefined;
  prop: string;
  field: string;
  list: any;
}

// todo replace by assets/js/functions/getMetadata.ts
const getMetadataValue = (prop: string, ignoreCase: boolean = true): string => {
  let v = "";
  prop = `_${TOKEN}_` + prop;
  if (userMetaWordpress?.[prop] && userMetaWordpress?.[prop].length > 0) {
    v = userMetaWordpress?.[prop][0];
  }
  return v.toUpperCase();
};

const UserComptaComponent: React.FC<State> = ({
  fComptet,
  prop,
  list,
  field,
}) => {
  const [userHasCtNum, setUserHasCtNum] = React.useState<boolean>(
    getMetadataValue("ctNum") !== "",
  );
  const getDefaultValue = (): FormState2 => {
    let value = getMetadataValue(prop);
    if (value === "") {
      if (!user || !userHasCtNum) {
        if (fComptet) {
          value = fComptet[prop].toString();
        } else {
          for (const key in list) {
            value = list[key].cbIndice;
            break;
          }
        }
      }
    }
    return {
      nCompta: { value: value },
    };
  };
  const [values, setValues] = React.useState<FormState2>(getDefaultValue());
  const [nComptaMetaDataValue, setNComptaMetaDataValue] =
    React.useState<string>(getMetadataValue(prop));
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
    for (const key in list) {
      if (list[key].cbIndice === fComptet[prop]) {
        labelSage = list[key][field];
        break;
      }
    }
  }
  React.useEffect(() => {
    setValues(getDefaultValue());
  }, [fComptet]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <tr>
      <th>
        <label htmlFor={`_${TOKEN}_` + prop}>
          {prop === "nCatCompta" && <>Catégorie comptable</>}
          {prop === "nCatTarif" && <>Catégorie tarifaire</>}
        </label>
      </th>
      <td>
        <select
          name={`_${TOKEN}_` + prop}
          id={`_${TOKEN}_` + prop}
          value={values.nCompta.value}
          onChange={handleChangeSelect("nCompta")}
        >
          <option value="" disabled={true}>
            Sélectionnez une option
          </option>
          {Object.entries(list).map((data) => {
            const compta: any = data[1];
            return (
              <option key={compta.cbIndice} value={compta.cbIndice}>
                {compta[field]}
              </option>
            );
          })}
        </select>
        {userHasCtNum &&
          fComptet &&
          fComptet[prop].toString() !== nComptaMetaDataValue && (
            <div>
              <span className="error-message">
                La catégorie renseignée dans Sage est différente de celle
                renseignée dans Wordpress.
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
  const [userHasCtNum, setUserHasCtNum] = React.useState<boolean>(
    getMetadataValue("ctNum") !== "",
  );
  const getDefaultValue = (): FormState => {
    const ctNum = getMetadataValue("ctNum");
    return {
      ctNum: { value: ctNum },
      autoGenerateCtNum: { value: true },
      creationType: {
        value:
          user && !userHasCtNum
            ? "none"
            : autoCreateSageFcomptet
              ? "new"
              : "none",
      },
    };
  };
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
        encodeURIComponent(`/${TOKEN}/v1/user/` + ctNum) +
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

  const showCreationType = !user || !userHasCtNum;
  const notValidCtNumExists =
    (fComptet &&
      getMetadataValue("ctNum") !== fComptet.ctNum &&
      values.creationType.value === "new") ||
    (fComptet === null && values.creationType.value === "link");
  const notValidCtNumAlreadyLink = thisUser && thisUser.ID !== user?.ID;
  const validCtNum =
    !notValidCtNumExists &&
    !notValidCtNumAlreadyLink &&
    ((fComptet && values.creationType.value === "link") ||
      (values.creationType.value === "new" &&
        (fComptet === null || values.autoGenerateCtNum.value)));
  const showCtNumField =
    (!!user && userHasCtNum) ||
    values.creationType.value === "link" ||
    (values.creationType.value === "new" && !values.autoGenerateCtNum.value);
  const showSageForm =
    (!!user && userHasCtNum) ||
    ((values.creationType.value === "link" ||
      values.creationType.value === "new") &&
      validCtNum);

  const validateForm = async (): Promise<boolean> => {
    let result = notValidCtNumExists || notValidCtNumAlreadyLink;
    let ctNumError = "";
    if (
      values.creationType.value === "link" ||
      (values.creationType.value === "new" && !values.autoGenerateCtNum.value)
    ) {
      ctNumError = await stringValidator({
        value: values.ctNum.value,
        maxLength: 19,
        canBeEmpty: false,
        canHaveSpace: false,
      });
    }
    if (result || ctNumError) {
      setValues((v) => {
        v.ctNum.error = ctNumError !== "" ? ctNumError : "notValid";
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
      !userHasCtNum &&
      ((values.autoGenerateCtNum.value &&
        values.creationType.value === "new") ||
        values.creationType.value === "none")
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
    $(document).on(
      "submit",
      'form[name="createuser"], form[id="your-profile"]',
      (e: any) => {
        // if (!validateForm()) {
        // todo doesn't work anymore as validateForm is async
        e.preventDefault();
        // }
      },
    );
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const checked = values.autoGenerateCtNum.value;
  return (
    <table className="form-table" role="presentation">
      <tbody>
        <tr>
          <th style={{ padding: 0 }}>
            <h2>Sage</h2>
          </th>
        </tr>
        {showCreationType && (
          <>
            <tr>
              <th>
                <label htmlFor={`_${TOKEN}_creationType`}>
                  Type d'utilisateur
                </label>
              </th>
              <td>
                <label htmlFor={`_${TOKEN}_creationType_none`}>
                  <input
                    type="radio"
                    name={`_${TOKEN}_creationType`}
                    value="none"
                    id={`_${TOKEN}_creationType_none`}
                    checked={values.creationType.value === "none"}
                    onChange={handleChangeRadio("creationType")}
                  />
                  Ne pas créer de compte Sage
                </label>
                <br />
                <br />
                <label htmlFor={`_${TOKEN}_creationType_new`}>
                  <input
                    type="radio"
                    name={`_${TOKEN}_creationType`}
                    value="new"
                    id={`_${TOKEN}_creationType_new`}
                    checked={values.creationType.value === "new"}
                    onChange={handleChangeRadio("creationType")}
                  />
                  Créer un nouveau compte Sage
                </label>
                <br />
                <br />
                <label htmlFor={`_${TOKEN}_creationType_link`}>
                  <input
                    type="radio"
                    name={`_${TOKEN}_creationType`}
                    value="link"
                    id={`_${TOKEN}_creationType_link`}
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
                  <label htmlFor={`_${TOKEN}_auto_generate_ctnum`}>
                    Générer le code client
                  </label>
                </th>
                <td>
                  <input
                    type="hidden"
                    name={`_${TOKEN}_auto_generate_ctnum`}
                    id={`_${TOKEN}_auto_generate_ctnum`}
                    value={checked ? "1" : "0"}
                  />
                  <input
                    type="checkbox"
                    value="1"
                    checked={checked}
                    onChange={handleChangeCheckbox("autoGenerateCtNum")}
                  />
                  <label htmlFor={`_${TOKEN}_auto_generate_ctnum`}>
                    Laisser l'API Sage générer le code client automatiquement.
                  </label>
                </td>
              </tr>
            )}
          </>
        )}
        {showCtNumField && (
          <tr>
            <th>
              <label htmlFor={`_${TOKEN}_ctNum`}>
                {translations.fComptets.ctNum}
              </label>
            </th>
            <td>
              <div style={{ position: "relative" }}>
                <input
                  type="text"
                  name={`_${TOKEN}_ctNum`}
                  id={`_${TOKEN}_ctNum`}
                  readOnly={userHasCtNum}
                  style={{
                    ...(!userHasCtNum &&
                      values.ctNum.error !== "" && {
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
                  <>
                    <span
                      className="dashicons dashicons-yes endDashiconsInput"
                      style={{ color: "green" }}
                    ></span>
                    <span>{fComptet?.ctIntitule}</span>
                  </>
                )}
                {(notValidCtNumExists || notValidCtNumAlreadyLink) && (
                  <>
                    <span
                      className="dashicons dashicons-no endDashiconsInput"
                      style={{ color: "red" }}
                    ></span>
                    {notValidCtNumExists && (
                      <>
                        <span>Ce compte Sage n'existe pas</span>
                      </>
                    )}
                    {notValidCtNumAlreadyLink && (
                      <>
                        {values.creationType.value === "link" && (
                          <>
                            <span>Ce compte Sage est déjà lié à </span>
                          </>
                        )}
                        {values.creationType.value === "new" && (
                          <>
                            <span>Ce compte Sage existe déjà </span>
                          </>
                        )}
                        {thisUser ? (
                          <a
                            href={
                              siteUrl +
                              "/wp-admin/user-edit.php?user_id=" +
                              thisUser.ID
                            }
                          >
                            {thisUser.data.display_name}
                          </a>
                        ) : (
                          <>
                            <button
                              type="button"
                              className="button"
                              onClick={() => {
                                setValues((v) => {
                                  return {
                                    ...v,
                                    creationType: {
                                      ...v.creationType,
                                      value: "link",
                                      error: "",
                                    },
                                  };
                                });
                              }}
                            >
                              Lier à ce compte
                            </button>
                          </>
                        )}
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
            <UserComptaComponent
              fComptet={fComptet}
              prop="nCatTarif"
              field="ctIntitule"
              list={pCattarifs}
            />
            <UserComptaComponent
              fComptet={fComptet}
              prop="nCatCompta"
              field="label"
              list={pCatComptas}
            />
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
